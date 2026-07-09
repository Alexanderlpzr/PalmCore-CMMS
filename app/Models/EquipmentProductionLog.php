<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EquipmentProductionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One production period (day/shift) for a piece of equipment, holding the raw
 * inputs for OEE. Availability, Performance, Quality and OEE are derived here so
 * the same math backs the resource, the widgets and the tests.
 */
#[Fillable([
    'tenant_id',
    'equipment_id',
    'log_date',
    'shift',
    'planned_minutes',
    'downtime_minutes',
    'ideal_rate_per_hour',
    'total_units',
    'good_units',
    'notes',
    'recorded_by',
])]
class EquipmentProductionLog extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EquipmentProductionLogFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── OEE components ──────────────────────────────────────────────────────────

    /** Operating time = planned time minus downtime (never negative), in minutes. */
    public function runMinutes(): float
    {
        return max(0.0, (float) $this->planned_minutes - (float) $this->downtime_minutes);
    }

    /** Availability = run time / planned time. Null when no planned time. */
    public function availability(): ?float
    {
        $planned = (float) $this->planned_minutes;

        if ($planned <= 0.0) {
            return null;
        }

        return min(1.0, $this->runMinutes() / $planned);
    }

    /**
     * Performance = actual output / theoretical output at full speed for the run
     * time. Capped at 1.0 so a low ideal-rate estimate cannot push OEE over 100%.
     * Null when there is no run time or ideal rate to measure against.
     */
    public function performance(): ?float
    {
        $idealRate = (float) $this->ideal_rate_per_hour;
        $runHours = $this->runMinutes() / 60.0;

        if ($idealRate <= 0.0 || $runHours <= 0.0) {
            return null;
        }

        $theoretical = $idealRate * $runHours;

        return min(1.0, (float) $this->total_units / $theoretical);
    }

    /** Quality = good units / total units produced. Null when nothing was produced. */
    public function quality(): ?float
    {
        $total = (float) $this->total_units;

        if ($total <= 0.0) {
            return null;
        }

        return min(1.0, (float) $this->good_units / $total);
    }

    /** OEE = Availability × Performance × Quality. Null when any factor is undefined. */
    public function oee(): ?float
    {
        $availability = $this->availability();
        $performance = $this->performance();
        $quality = $this->quality();

        if ($availability === null || $performance === null || $quality === null) {
            return null;
        }

        return $availability * $performance * $quality;
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'planned_minutes' => 'integer',
            'downtime_minutes' => 'integer',
            'ideal_rate_per_hour' => 'decimal:4',
            'total_units' => 'decimal:2',
            'good_units' => 'decimal:2',
        ];
    }
}
