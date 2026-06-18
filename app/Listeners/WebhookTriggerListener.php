<?php

namespace App\Listeners;

use App\Contracts\WebhookableEvent;
use App\Domain\Webhooks\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class WebhookTriggerListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(WebhookableEvent $event): void
    {
        Log::withContext([
            'event_class' => get_class($event),
            'tenant_id' => $event->webhookTenantId(),
        ]);
        Log::info('webhook.trigger_dispatched');

        $this->dispatcher->dispatch(
            $event->webhookEventName(),
            $event->webhookPayload(),
            $event->webhookTenantId(),
        );
    }
}
