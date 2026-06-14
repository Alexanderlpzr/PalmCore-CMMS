<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\MaintenanceChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'maintenance_plan_task_id',
    'sort_order',
    'label',
    'item_type',
    'unit',
    'expected_min',
    'expected_max',
    'is_required',
])]
class MaintenanceChecklistItem extends BaseModel
{
    /** @use HasFactory<MaintenanceChecklistItemFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function task(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlanTask::class, 'maintenance_plan_task_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function hasRange(): bool
    {
        return $this->item_type->hasRange()
            && ($this->expected_min !== null || $this->expected_max !== null);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'item_type' => MaintenanceChecklistItemType::class,
            'is_required' => 'boolean',
            'expected_min' => 'float',
            'expected_max' => 'float',
        ];
    }
}
