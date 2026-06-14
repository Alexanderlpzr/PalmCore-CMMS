<?php

namespace App\Listeners;

use App\Contracts\WebhookableEvent;
use App\Domain\Webhooks\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class WebhookTriggerListener implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly WebhookDispatcher $dispatcher) {}

    public function handle(WebhookableEvent $event): void
    {
        $this->dispatcher->dispatch(
            $event->webhookEventName(),
            $event->webhookPayload(),
            $event->webhookTenantId(),
        );
    }
}
