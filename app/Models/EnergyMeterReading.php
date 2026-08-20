<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\EnergyMeterReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una lectura diaria de un contador de energía.
 *
 * `reading_value` es lo que marca el dial; `delta` es el consumo desde la lectura
 * anterior. Guardar los dos es lo que hace que el consumo del mes se pueda auditar
 * contra el contador — y lo que impide el error que traía la hoja de cálculo, donde dos
 * fórmulas de delta restaban la fila equivocada e inflaron la turbina en 3.706 kWh.
 *
 * Sin soft deletes: una lectura es un hecho histórico.
 */
#[Fillable([
    'tenant_id',
    'energy_meter_id',
    'reading_date',
    'reading_value',
    'previous_value',
    'delta',
    'accumulated_value',
    'is_reset',
    'recorded_by',
    'notes',
])]
class EnergyMeterReading extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EnergyMeterReadingFactory> */
    use HasFactory;

    use HasUuids;

    // ── Relationships ─────────────────────────────────────────────────────────

    public function energyMeter(): BelongsTo
    {
        return $this->belongsTo(EnergyMeter::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'reading_value' => 'float',
            'previous_value' => 'float',
            'delta' => 'float',
            'accumulated_value' => 'float',
            'is_reset' => 'boolean',
        ];
    }
}
