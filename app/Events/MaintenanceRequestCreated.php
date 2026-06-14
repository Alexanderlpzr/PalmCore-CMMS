<?php

namespace App\Events;

use App\Contracts\WebhookableEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\MaintenanceRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MaintenanceRequestCreated implements WebhookableEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly MaintenanceRequest $request) {}

    public function webhookEventName(): string
    {
        return WebhookEvent::MaintenanceRequestCreated->value;
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->request->id,
            'tenant_id' => $this->request->tenant_id,
            'request_number' => $this->request->request_number,
            'title' => $this->request->title,
            'status' => $this->request->status?->value,
            'request_type' => $this->request->request_type?->value,
            'priority' => $this->request->priority?->value,
            'equipment_id' => $this->request->equipment_id,
            'created_at' => $this->request->created_at?->toIso8601String(),
        ];
    }

    public function webhookTenantId(): string
    {
        return $this->request->tenant_id;
    }
}
