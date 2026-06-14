<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WorkOrderPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'work_order_id',
    'spare_part_id',
    'warehouse_id',
    'part_code',
    'description',
    'quantity',
    'unit',
    'unit_cost',
    'total_cost',
    'status',
    'reserved_quantity',
    'issued_quantity',
    'returned_quantity',
    'unit_cost_snapshot',
])]
class WorkOrderPart extends BaseModel
{
    /** @use HasFactory<WorkOrderPartFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function hasInventoryLink(): bool
    {
        return $this->spare_part_id !== null && $this->warehouse_id !== null;
    }

    public function computedTotalCost(): float
    {
        if ($this->unit_cost === null) {
            return 0.0;
        }

        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }

    public function remainingToReturn(): float
    {
        return max(0.0, (float) $this->issued_quantity - (float) $this->returned_quantity);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'status' => WorkOrderPartStatus::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'reserved_quantity' => 'decimal:4',
            'issued_quantity' => 'decimal:4',
            'returned_quantity' => 'decimal:4',
            'unit_cost_snapshot' => 'decimal:4',
        ];
    }
}
