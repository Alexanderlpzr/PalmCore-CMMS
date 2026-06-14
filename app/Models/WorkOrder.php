<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Shared\Models\BaseModel;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'work_order_number',
    'maintenance_request_id',
    'maintenance_plan_id',
    'equipment_id',
    'plant_id',
    'area_id',
    'work_order_type',
    'status',
    'priority',
    'title',
    'description',
    'instructions',
    'failure_cause',
    'work_performed',
    'root_cause',
    'rejection_reason',
    'equipment_stopped',
    'downtime_minutes',
    'planned_start_at',
    'planned_end_at',
    'planned_labor_hours',
    'actual_start_at',
    'actual_end_at',
    'actual_labor_hours',
    'estimated_cost',
    'actual_cost_labor',
    'actual_cost_parts',
    'actual_cost_external',
    'actual_cost_total',
    'currency_code',
    'created_by',
    'assigned_supervisor',
    'completed_by',
    'verified_by',
    'started_at',
    'completed_at',
    'verified_at',
    'closed_at',
])]
class WorkOrder extends BaseModel
{
    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_supervisor');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(WorkOrderTechnician::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WorkOrderTimeLog::class)->orderBy('started_at');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkOrderAttachment::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(WorkOrderSignature::class);
    }

    public function technicianSignature(): HasOne
    {
        return $this->hasOne(WorkOrderSignature::class)
            ->where('signature_type', 'technician_completion');
    }

    public function supervisorSignature(): HasOne
    {
        return $this->hasOne(WorkOrderSignature::class)
            ->where('signature_type', 'supervisor_verification');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            WorkOrderStatus::Closed->value,
            WorkOrderStatus::Cancelled->value,
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WorkOrderStatus::InProgress->value);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canTransitionTo(WorkOrderStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    public function totalActualCost(): float
    {
        return (float) (($this->actual_cost_labor ?? 0)
            + ($this->actual_cost_parts ?? 0)
            + ($this->actual_cost_external ?? 0));
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'work_order_type' => WorkOrderType::class,
            'status' => WorkOrderStatus::class,
            'priority' => WorkOrderPriority::class,
            'equipment_stopped' => 'boolean',
            'planned_labor_hours' => 'float',
            'actual_labor_hours' => 'float',
            'estimated_cost' => 'float',
            'actual_cost_labor' => 'float',
            'actual_cost_parts' => 'float',
            'actual_cost_external' => 'float',
            'actual_cost_total' => 'float',
            'planned_start_at' => 'datetime',
            'planned_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
