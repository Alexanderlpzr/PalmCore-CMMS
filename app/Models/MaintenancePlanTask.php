<?php

namespace App\Models;

use App\Domain\Shared\Models\BaseModel;
use Database\Factories\MaintenancePlanTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'maintenance_plan_id',
    'sort_order',
    'title',
    'description',
    'estimated_minutes',
])]
class MaintenancePlanTask extends BaseModel
{
    /** @use HasFactory<MaintenancePlanTaskFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(MaintenanceChecklistItem::class)->orderBy('sort_order');
    }
}
