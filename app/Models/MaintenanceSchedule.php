<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'maintenance_plan_id',
    'last_completed_at',
    'last_completed_meter',
    'next_due_at',
    'next_due_meter',
    'times_executed',
    'times_skipped',
    'last_work_order_id',
])]
class MaintenanceSchedule extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MaintenanceScheduleFactory> */
    use HasFactory;

    use HasUuids;

    // No soft deletes — operational record, never deleted

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function lastWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'last_work_order_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isOverdueByTime(): bool
    {
        return $this->next_due_at !== null && $this->next_due_at->isPast();
    }

    public function isOverdueByMeter(float $currentReading): bool
    {
        return $this->next_due_meter !== null && $currentReading >= $this->next_due_meter;
    }

    public function isOverdue(?float $currentMeterReading = null): bool
    {
        if ($this->isOverdueByTime()) {
            return true;
        }

        if ($currentMeterReading !== null && $this->isOverdueByMeter($currentMeterReading)) {
            return true;
        }

        return false;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'last_completed_at' => 'datetime',
            'next_due_at' => 'datetime',
            'last_completed_meter' => 'float',
            'next_due_meter' => 'float',
        ];
    }
}
