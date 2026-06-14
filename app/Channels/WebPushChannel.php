<?php

namespace App\Channels;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WebPushChannel
{
    public function __construct(private readonly WebPushService $service) {}

    public function send(object $notifiable, Notification $notification): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $notification->toWebPush($notifiable, $notification);

        $tenantId = $payload['tenant_id'] ?? null;

        if ($tenantId === null) {
            return;
        }

        // Always bypass global tenant scope — notifications run in queue context
        // where no tenant middleware is active.
        $subscriptions = PushSubscription::withoutGlobalScopes()
            ->where('user_id', $notifiable->id)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($subscriptions as $subscription) {
            $result = $this->service->send($subscription, $payload);

            if ($result === 'ok') {
                $subscription->update(['last_used_at' => now()]);
            } elseif ($result === 'expired') {
                $subscription->update(['is_active' => false]);
            } else {
                Log::warning('WebPush send failed', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $notifiable->id,
                ]);
            }
        }
    }
}
