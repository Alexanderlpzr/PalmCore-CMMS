<?php

namespace App\Events;

use App\Contracts\WebhookableEvent;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\WorkOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderStatusChanged implements WebhookableEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WorkOrder $workOrder,
        public readonly WorkOrderStatus $toStatus,
    ) {}

    public function webhookEventName(): string
    {
        return match ($this->toStatus) {
            WorkOrderStatus::Completed => WebhookEvent::WorkOrderCompleted->value,
            WorkOrderStatus::Closed => WebhookEvent::WorkOrderClosed->value,
            default => WebhookEvent::WorkOrderCompleted->value,
        };
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->workOrder->id,
            'tenant_id' => $this->workOrder->tenant_id,
            'work_order_number' => $this->workOrder->work_order_number,
            'title' => $this->workOrder->title,
            'status' => $this->toStatus->value,
            'equipment_id' => $this->workOrder->equipment_id,
            'updated_at' => $this->workOrder->updated_at?->toIso8601String(),
        ];
    }

    public function webhookTenantId(): string
    {
        return $this->workOrder->tenant_id;
    }
}
