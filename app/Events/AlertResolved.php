<?php

namespace App\Events;

use App\Contracts\WebhookableEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\Alert;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertResolved implements WebhookableEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Alert $alert) {}

    public function webhookEventName(): string
    {
        return WebhookEvent::AlertResolved->value;
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->alert->id,
            'tenant_id' => $this->alert->tenant_id,
            'severity' => $this->alert->severity?->value,
            'category' => $this->alert->category?->value,
            'title' => $this->alert->title,
            'status' => $this->alert->status?->value,
            'entity_type' => $this->alert->entity_type,
            'entity_id' => $this->alert->entity_id,
            'closed_at' => $this->alert->closed_at?->toIso8601String(),
        ];
    }

    public function webhookTenantId(): string
    {
        return $this->alert->tenant_id;
    }
}
