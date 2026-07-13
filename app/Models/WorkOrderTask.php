<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\WorkOrderTaskStatus;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WorkOrderTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'work_order_id',
    'maintenance_plan_task_id',
    'sort_order',
    'title',
    'description',
    'estimated_minutes',
    'status',
    'skipped_reason',
    'assigned_to',
    'started_at',
    'completed_at',
    'completed_by',
])]
class WorkOrderTask extends BaseModel
{
    /** @use HasFactory<WorkOrderTaskFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** Provenance only — the executable copy lives on this row. */
    public function planTask(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlanTask::class, 'maintenance_plan_task_id');
    }

    public function checklistResults(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistResult::class)->orderBy('sort_order');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WorkOrderTaskStatus::Pending->value,
            WorkOrderTaskStatus::InProgress->value,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Required checklist items still awaiting a value. */
    public function missingRequiredResults(): int
    {
        return $this->checklistResults()
            ->where('is_required', true)
            ->whereNull('recorded_at')
            ->count();
    }

    public function hasDeviations(): bool
    {
        return $this->checklistResults()->where('is_out_of_range', true)->exists();
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'status' => WorkOrderTaskStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
