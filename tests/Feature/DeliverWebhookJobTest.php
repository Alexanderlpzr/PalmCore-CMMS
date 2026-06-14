<?php

use App\Domain\Alerts\Services\AlertService;
use App\Jobs\DeliverWebhookJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDeliveryLog;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// ── Helpers ────────────────────────────────────────────────────────────────────

function webhookSub(string $tenantId, bool $isActive = true, int $failureCount = 0): WebhookSubscription
{
    $user = User::factory()->create(['is_active' => true]);

    return WebhookSubscription::forceCreate([
        'tenant_id' => $tenantId,
        'url' => 'https://example.com/hook',
        'events' => ['alert.created'],
        'secret' => 'test-secret-key-1234567890abcdef',
        'is_active' => $isActive,
        'failure_count' => $failureCount,
        'created_by' => $user->id,
    ]);
}

function runDeliverJob(WebhookSubscription $sub): void
{
    $job = new DeliverWebhookJob(
        subscriptionId: $sub->id,
        eventName: 'alert.created',
        payload: ['id' => 'test-123'],
        eventId: 'evt-'.uniqid(),
    );
    $job->handle(app(AlertService::class));
}

// ── Delivery exitoso ───────────────────────────────────────────────────────────

it('delivery exitoso: resetea failure_count y registra log success', function () {
    Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id, isActive: true, failureCount: 2);

    runDeliverJob($sub);

    expect($sub->fresh()->failure_count)->toBe(0)
        ->and($sub->fresh()->last_triggered_at)->not->toBeNull()
        ->and($sub->fresh()->last_error)->toBeNull();

    expect(WebhookDeliveryLog::where('webhook_subscription_id', $sub->id)->first())
        ->status->toBe('success')
        ->http_status->toBe(200);
});

it('delivery exitoso: X-Fronda-Signature usa HMAC-SHA256 con el secret correcto', function () {
    Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id);

    runDeliverJob($sub);

    Http::assertSent(function ($request) {
        $body = $request->body();
        $expected = 'sha256='.hash_hmac('sha256', $body, 'test-secret-key-1234567890abcdef');

        return $request->hasHeader('X-Fronda-Signature', $expected)
            && $request->hasHeader('X-Fronda-Event', 'alert.created')
            && $request->hasHeader('X-Fronda-Event-Id');
    });
});

// ── Webhook inactivo ──────────────────────────────────────────────────────────

it('abort silenciosamente si el webhook fue desactivado antes del delivery', function () {
    Http::fake();

    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id, isActive: false);

    runDeliverJob($sub);

    Http::assertNothingSent();
    expect(WebhookDeliveryLog::count())->toBe(0);
});

// ── 4xx — error permanente ────────────────────────────────────────────────────

it('4xx: llama fail() sin lanzar excepción para evitar retry', function () {
    Http::fake(['https://example.com/hook' => Http::response('Not Found', 404)]);

    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id);

    $job = new DeliverWebhookJob($sub->id, 'alert.created', ['id' => 'x'], 'evt-123');

    // fail() is called internally — handle() should not throw
    $job->handle(app(AlertService::class));

    expect(WebhookDeliveryLog::where('webhook_subscription_id', $sub->id)->first())
        ->status->toBe('failed')
        ->http_status->toBe(404);
});

// ── 5xx — retry ───────────────────────────────────────────────────────────────

it('5xx: lanza RuntimeException para activar retry del job', function () {
    Http::fake(['https://example.com/hook' => Http::response('Server Error', 500)]);

    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id);

    $job = new DeliverWebhookJob($sub->id, 'alert.created', [], 'evt-456');

    expect(fn () => $job->handle(app(AlertService::class)))->toThrow(RuntimeException::class);
});

// ── Auto-desactivación ────────────────────────────────────────────────────────

it('failed() desactiva el webhook cuando failure_count alcanza el threshold', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id, failureCount: WebhookSubscription::DEACTIVATION_THRESHOLD - 1);

    $job = new DeliverWebhookJob($sub->id, 'alert.created', [], 'evt-789');
    $job->failed(new RuntimeException('HTTP 503'));

    expect($sub->fresh()->is_active)->toBeFalse()
        ->and($sub->fresh()->failure_count)->toBe(WebhookSubscription::DEACTIVATION_THRESHOLD);
});

it('failed() incrementa failure_count sin desactivar si no llega al threshold', function () {
    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id, failureCount: 1);

    $job = new DeliverWebhookJob($sub->id, 'alert.created', [], 'evt-000');
    $job->failed(new RuntimeException('HTTP 503'));

    expect($sub->fresh()->is_active)->toBeTrue()
        ->and($sub->fresh()->failure_count)->toBe(2);
});

// ── Poda de logs ──────────────────────────────────────────────────────────────

it('mantiene max 50 logs por subscripción después de cada delivery exitoso', function () {
    Http::fake(['https://example.com/hook' => Http::response('OK', 200)]);

    $tenant = Tenant::factory()->create();
    $sub = webhookSub($tenant->id);

    // Pre-existentes: 55 logs
    for ($i = 0; $i < 55; $i++) {
        WebhookDeliveryLog::forceCreate([
            'webhook_subscription_id' => $sub->id,
            'event_id' => 'evt-old-'.$i,
            'event_name' => 'alert.created',
            'status' => 'success',
        ]);
    }

    runDeliverJob($sub);

    expect(WebhookDeliveryLog::where('webhook_subscription_id', $sub->id)->count())->toBe(50);
});
