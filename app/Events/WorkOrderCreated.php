<?php

namespace App\Events;

use App\Contracts\WebhookableEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\WorkOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderCreated implements WebhookableEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly WorkOrder $workOrder) {}

    public function webhookEventName(): string
    {
        return WebhookEvent::WorkOrderCreated->value;
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->workOrder->id,
            'tenant_id' => $this->workOrder->tenant_id,
            'work_order_number' => $this->workOrder->work_order_number,
            'title' => $this->workOrder->title,
            'status' => $this->workOrder->status?->value,
            'work_order_type' => $this->workOrder->work_order_type?->value,
            'priority' => $this->workOrder->priority?->value,
            'equipment_id' => $this->workOrder->equipment_id,
            'created_at' => $this->workOrder->created_at?->toIso8601String(),
        ];
    }

    public function webhookTenantId(): string
    {
        return $this->workOrder->tenant_id;
    }
}
