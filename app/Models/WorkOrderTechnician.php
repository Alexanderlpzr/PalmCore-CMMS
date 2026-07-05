<?php

namespace App\Models;

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\WorkOrderTechnicianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'work_order_id',
    'user_id',
    'role',
    'planned_hours',
    'hourly_rate',
    'notes',
])]
class WorkOrderTechnician extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    // No soft deletes — removal is operational

    /** @use HasFactory<WorkOrderTechnicianFactory> */

    // ── Relationships ─────────────────────────────────────────────────────────

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Real hours worked, from time logs — counts entries with an explicit
     * `hours` value as well as open/closed shifts computed from
     * started_at/ended_at (WorkOrderTimeLog::computedHours()).
     */
    public function actualHours(): float
    {
        return (float) $this->workOrder->timeLogs()
            ->where('user_id', $this->user_id)
            ->get()
            ->sum(fn (WorkOrderTimeLog $log): float => $log->computedHours());
    }

    public function laborCost(): float
    {
        if ($this->hourly_rate === null) {
            return 0.0;
        }

        return $this->actualHours() * (float) $this->hourly_rate;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'role' => TechnicianRole::class,
        ];
    }
}
