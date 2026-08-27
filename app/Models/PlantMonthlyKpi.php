<?php

namespace App\Models;

use App\Domain\Shared\Concerns\Auditable;
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
 * Los tres indicadores —`efficiency_percentage`, `productivity_tons_per_hour` y
 * `availability_percentage`— están deliberadamente fuera de #[Fillable]:
 * PostgreSQL los deriva de las horas y las toneladas, así que el número que ve
 * gerencia nunca puede separarse de las cifras con las que se calculó.
 *
 * `processed_tons_is_manual` sí es escribible: marca el mes cuyas toneladas
 * alguien corrigió a mano, y {@see PlantKpiService::snapshotMonth()} lo respeta
 * al recalcular.
 *
 * Lo mismo vale para la energía: `kwh_total`, `kwh_per_ton` y
 * `clean_energy_percentage` son columnas generadas, y `energy_is_imported` marca los
 * meses que vinieron de la hoja histórica para que el cierre no los pise recalculando
 * sobre lecturas diarias que nunca existieron.
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
    'cleaning_hours',
    'processed_tons',
    'processed_tons_is_manual',
    'kwh_grid',
    'kwh_genset',
    'kwh_turbine',
    'energy_is_imported',
    'failure_count',
    'mtbf_hours',
    'mttr_hours',
    'calculated_at',
])]
class PlantMonthlyKpi extends Model
{
    /**
     * Estas cifras se pueden corregir a mano desde la planilla, y son las que van a
     * gerencia. Sin rastro de quién cambió qué y desde qué valor, una corrección
     * legítima y un número inventado se ven exactamente igual.
     *
     * El modelo no hereda de BaseModel —no tiene borrado lógico ni lo necesita, es un
     * cierre— así que el trait se pone aquí explícitamente.
     */
    use Auditable;

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

    /**
     * HMTTO — horas de paro por mantenimiento no programado.
     *
     * Se deriva por resta en vez de guardarse: `maintenance_lost_hours` es la
     * unión de todos los paros de mantenimiento y `cleaning_hours` la de los
     * programados, así que restarlas da las horas correctivas sin contar dos
     * veces un solapamiento.
     */
    public function unplannedMaintenanceHours(): float
    {
        return max(0.0, round($this->maintenance_lost_hours - $this->cleaning_hours, 2));
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
            'cleaning_hours' => 'float',
            'processed_tons' => 'float',
            'processed_tons_is_manual' => 'boolean',
            'efficiency_percentage' => 'float',
            'productivity_tons_per_hour' => 'float',
            'availability_percentage' => 'float',
            'kwh_grid' => 'float',
            'kwh_genset' => 'float',
            'kwh_turbine' => 'float',
            'energy_is_imported' => 'boolean',
            'kwh_total' => 'float',
            'kwh_per_ton' => 'float',
            'clean_energy_percentage' => 'float',
            'failure_count' => 'integer',
            'mtbf_hours' => 'float',
            'mttr_hours' => 'float',
            'calculated_at' => 'datetime',
        ];
    }
}
