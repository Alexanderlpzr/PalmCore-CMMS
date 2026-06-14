<?php

namespace App\Events;

use App\Contracts\WebhookableEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\SparePart;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryLowStockDetected implements WebhookableEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly SparePart $sparePart,
        public readonly float $currentStock,
        public readonly float $reorderPoint,
    ) {}

    public function webhookEventName(): string
    {
        return WebhookEvent::InventoryLowStock->value;
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->sparePart->id,
            'tenant_id' => $this->sparePart->tenant_id,
            'code' => $this->sparePart->code,
            'name' => $this->sparePart->name,
            'current_stock' => $this->currentStock,
            'reorder_point' => $this->reorderPoint,
            'detected_at' => now()->toIso8601String(),
        ];
    }

    public function webhookTenantId(): string
    {
        return $this->sparePart->tenant_id;
    }
}
