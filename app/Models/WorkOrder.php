<?php

namespace App\Models;

use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\PlantProcess;
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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tenant_id',
    'work_order_number',
    'maintenance_request_id',
    'issue_report_id',
    'maintenance_plan_id',
    'equipment_id',
    'plant_id',
    'area_id',
    'work_order_type',
    'process',
    'maintenance_area',
    'executed_by',
    'meter_reading',
    'status',
    'priority',
    'title',
    'description',
    'instructions',
    'failure_cause',
    'work_performed',
    'root_cause',
    'failure_mode',
    'diagnosed_stoppage_category',
    'rejection_reason',
    'equipment_stopped',
    'required_permit_types',
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
    'equipment_component_id',
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

    /** El reporte de novedad que originó esta OT, si nació de uno. */
    public function issueReport(): BelongsTo
    {
        return $this->belongsTo(EquipmentIssueReport::class, 'issue_report_id');
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(EquipmentComponent::class, 'equipment_component_id');
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

    /** Los terceros que ejecutan esta OT: no están en la nómina, pero cuestan. */
    public function contractors(): HasMany
    {
        return $this->hasMany(WorkOrderContractor::class);
    }

    /** Los permisos de alto riesgo. Sin ellos, esta OT no arranca. */
    public function permits(): HasMany
    {
        return $this->hasMany(WorkPermit::class);
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WorkOrderTimeLog::class)->orderBy('started_at');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    /** The work itself: a frozen copy of the plan's tasks, executable in the field. */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkOrderTask::class)->orderBy('sort_order');
    }

    public function checklistResults(): HasManyThrough
    {
        return $this->hasManyThrough(
            WorkOrderChecklistResult::class,
            WorkOrderTask::class,
            'work_order_id',
            'work_order_task_id',
        );
    }

    public function downtimeEvents(): HasMany
    {
        return $this->hasMany(EquipmentDowntimeEvent::class);
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

    /**
     * Signed cost variance: actual total minus the estimate. Positive means the
     * job ran over budget (sobrecosto), negative means it came in under.
     * Null when either side is missing — there is no baseline to compare against.
     */
    public function costVariance(): ?float
    {
        if ($this->estimated_cost === null || $this->actual_cost_total === null) {
            return null;
        }

        return round((float) $this->actual_cost_total - (float) $this->estimated_cost, 2);
    }

    /**
     * Cost variance as a percentage of the estimate. Null when there is no
     * estimate (or it is zero) to divide by.
     */
    public function costVariancePercentage(): ?float
    {
        $variance = $this->costVariance();

        if ($variance === null || (float) $this->estimated_cost == 0.0) {
            return null;
        }

        return round($variance / (float) $this->estimated_cost * 100, 1);
    }

    /**
     * Falls back to the planned_start_at/planned_end_at interval when no one
     * typed an explicit planned_labor_hours value on the form.
     */
    public function plannedHours(): ?float
    {
        if ($this->planned_labor_hours !== null) {
            return (float) $this->planned_labor_hours;
        }

        if ($this->planned_start_at && $this->planned_end_at) {
            return round(abs($this->planned_start_at->diffInMinutes($this->planned_end_at)) / 60, 2);
        }

        return null;
    }

    /**
     * Falls back to the actual_start_at/actual_end_at interval when the
     * técnico's time logs didn't produce a stored actual_labor_hours total
     * (e.g. the WO was closed through a path that never opened a time log).
     */
    public function actualHours(): ?float
    {
        if ($this->actual_labor_hours !== null) {
            return (float) $this->actual_labor_hours;
        }

        if ($this->actual_start_at && $this->actual_end_at) {
            return round(abs($this->actual_start_at->diffInMinutes($this->actual_end_at)) / 60, 2);
        }

        return null;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'work_order_type' => WorkOrderType::class,
            'process' => PlantProcess::class,
            'maintenance_area' => MaintenanceArea::class,
            'meter_reading' => 'float',
            'status' => WorkOrderStatus::class,
            'priority' => WorkOrderPriority::class,
            'failure_mode' => FailureMode::class,
            'diagnosed_stoppage_category' => StoppageCategory::class,
            'equipment_stopped' => 'boolean',
            'required_permit_types' => 'array',
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
