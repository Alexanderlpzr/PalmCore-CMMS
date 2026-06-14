<?php

namespace App\Domain\Webhooks\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookSubscription;
use Illuminate\Support\Str;

class WebhookDispatcher
{
    /**
     * Query active subscriptions for the tenant + event, then enqueue one DeliverWebhookJob per match.
     * The tenantId is explicit (never from a global/static context) for safe use inside queue workers.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $eventName, array $payload, string $tenantId): void
    {
        $subscriptions = WebhookSubscription::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereJsonContains('events', $eventName)
            ->get();

        foreach ($subscriptions as $subscription) {
            $eventId = (string) Str::uuid7();

            DeliverWebhookJob::dispatch($subscription->id, $eventName, $payload, $eventId)
                ->onQueue('webhooks')
                ->afterCommit();
        }
    }
}
