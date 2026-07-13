<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\PlantMonthlyKpiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A closed month, frozen.
 *
 * `efficiency_percentage` is deliberately absent from #[Fillable]: PostgreSQL
 * derives it from the hours, so the number management sees can never drift from
 * the numbers it was computed with.
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'year',
    'month',
    'programmed_hours',
    'lost_hours',
    'effective_hours',
    'maintenance_lost_hours',
    'failure_count',
    'mtbf_hours',
    'mttr_hours',
    'calculated_at',
])]
class PlantMonthlyKpi extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PlantMonthlyKpiFactory> */
    use HasFactory;

    use HasUuids;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'programmed_hours' => 'float',
            'lost_hours' => 'float',
            'effective_hours' => 'float',
            'maintenance_lost_hours' => 'float',
            'efficiency_percentage' => 'float',
            'failure_count' => 'integer',
            'mtbf_hours' => 'float',
            'mttr_hours' => 'float',
            'calculated_at' => 'datetime',
        ];
    }
}
