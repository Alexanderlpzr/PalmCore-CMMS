<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WorkOrderPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'work_order_id',
    'part_code',
    'description',
    'quantity',
    'unit',
    'unit_cost',
    'total_cost',
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

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function computedTotalCost(): float
    {
        if ($this->unit_cost === null) {
            return 0.0;
        }

        return round((float) $this->quantity * (float) $this->unit_cost, 2);
    }
}
