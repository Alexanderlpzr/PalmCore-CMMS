<?php

namespace App\Jobs;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Models\WebhookDeliveryLog;
use App\Models\WebhookSubscription;
use App\Security\SsrfValidator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class DeliverWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 35;

    /** Exponential backoff: 1 min, 5 min, 15 min. */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $eventName,
        public readonly array $payload,
        public readonly string $eventId,
    ) {
        $this->onQueue('webhooks');
    }

    /** One delivery attempt per (subscription, event) at a time. */
    public function uniqueId(): string
    {
        return "webhook-{$this->subscriptionId}-{$this->eventId}";
    }

    public function handle(AlertService $alertService): void
    {
        Log::withContext([
            'webhook_subscription_id' => $this->subscriptionId,
            'event_name' => $this->eventName,
            'event_id' => $this->eventId,
        ]);

        $subscription = WebhookSubscription::withoutGlobalScopes()->find($this->subscriptionId);

        if (! $subscription || ! $subscription->is_active) {
            Log::warning('webhook.delivery_skipped', ['reason' => 'inactive_or_missing']);

            return;
        }

        Log::info('webhook.delivery_started');

        try {
            SsrfValidator::validate($subscription->url);
        } catch (InvalidArgumentException $e) {
            $subscription->forceFill([
                'is_active' => false,
                'last_error' => 'URL bloqueada por política SSRF: '.$e->getMessage(),
            ])->save();

            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, $subscription->secret);

        $log = WebhookDeliveryLog::create([
            'webhook_subscription_id' => $subscription->id,
            'event_id' => $this->eventId,
            'event_name' => $this->eventName,
            'status' => 'pending',
        ]);

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Fronda-Signature' => $signature,
                'X-Fronda-Event-Id' => $this->eventId,
                'X-Fronda-Event' => $this->eventName,
            ])
                ->timeout(30)
                ->connectTimeout(5)
                ->post($subscription->url, $this->payload);
        } catch (ConnectionException $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);
            $this->recordFailure($log, $subscription, null, $durationMs, $e->getMessage());
            throw $e;  // triggers retry
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        if ($response->successful()) {
            $this->recordSuccess($log, $subscription, $response->status(), $durationMs, $response->body());

            return;
        }

        $errorMessage = "HTTP {$response->status()}";
        $this->recordFailure($log, $subscription, $response->status(), $durationMs, $errorMessage);

        if ($response->clientError()) {
            // 4xx = permanent failure, do not retry
            $this->fail(new \RuntimeException($errorMessage));

            return;
        }

        // 5xx = retry
        throw new \RuntimeException($errorMessage);
    }

    public function failed(Throwable $exception): void
    {
        $subscription = WebhookSubscription::withoutGlobalScopes()->find($this->subscriptionId);

        if (! $subscription || ! $subscription->is_active) {
            return;
        }

        $newCount = $subscription->failure_count + 1;

        if ($newCount >= WebhookSubscription::DEACTIVATION_THRESHOLD) {
            $subscription->forceFill([
                'is_active' => false,
                'failure_count' => $newCount,
                'last_error' => substr($exception->getMessage(), 0, 500),
            ])->save();

            $this->createDeactivationAlert($subscription);
        } else {
            $subscription->forceFill([
                'failure_count' => $newCount,
                'last_error' => substr($exception->getMessage(), 0, 500),
            ])->save();
        }
    }

    private function recordSuccess(
        WebhookDeliveryLog $log,
        WebhookSubscription $subscription,
        int $httpStatus,
        int $durationMs,
        string $responseBody,
    ): void {
        $log->forceFill([
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'response_size' => strlen($responseBody),
            'status' => 'success',
            'delivered_at' => now(),
        ])->save();

        $subscription->forceFill([
            'failure_count' => 0,
            'last_triggered_at' => now(),
            'last_error' => null,
        ])->save();

        $this->pruneOldLogs($subscription->id);
    }

    private function recordFailure(
        WebhookDeliveryLog $log,
        WebhookSubscription $subscription,
        ?int $httpStatus,
        int $durationMs,
        string $errorMessage,
    ): void {
        $log->forceFill([
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'status' => 'failed',
            'delivered_at' => now(),
        ])->save();

        $subscription->forceFill([
            'last_error' => substr($errorMessage, 0, 500),
        ])->save();
    }

    private function pruneOldLogs(string $subscriptionId): void
    {
        $keepIds = WebhookDeliveryLog::where('webhook_subscription_id', $subscriptionId)
            ->latest()
            ->limit(50)
            ->pluck('id');

        WebhookDeliveryLog::where('webhook_subscription_id', $subscriptionId)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function createDeactivationAlert(WebhookSubscription $subscription): void
    {
        app(AlertService::class)->create(new CreateAlertData(
            tenantId: $subscription->tenant_id,
            severity: AlertSeverity::Warning,
            category: AlertCategory::System,
            title: "Webhook desactivado: {$subscription->url}",
            message: "Webhook desactivado tras {$subscription->failure_count} fallos consecutivos. Último error: {$subscription->last_error}. Reactivar desde Integraciones.",
            entityType: 'webhook_subscription',
            entityId: $subscription->id,
        ));
    }
}
