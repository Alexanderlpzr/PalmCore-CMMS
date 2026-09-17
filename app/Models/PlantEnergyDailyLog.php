<?php

namespace App\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use Database\Factories\PlantEnergyDailyLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que la ronda diaria anota de la planta eléctrica y no es la lectura de un contador:
 * los galones cargados y las veces que se cambió de fuente.
 *
 * No están en {@see EnergyMeterReading} porque allí todo gira alrededor de un dial que se
 * resta contra el anterior, y aquí no hay dial: son cantidades del día. Las **horas**
 * tampoco están aquí — salen del horómetro del generador, que ya es un dato del equipo.
 *
 * Una fila por planta y día. Cero galones es un dato; el campo vacío es que nadie anotó.
 */
#[Fillable([
    'tenant_id',
    'plant_id',
    'log_date',
    'fuel_gallons',
    'energy_switch_count',
    'recorded_by',
    'notes',
])]
class PlantEnergyDailyLog extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PlantEnergyDailyLogFactory> */
    use HasFactory;

    use HasUuids;

    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'fuel_gallons' => 'float',
            'energy_switch_count' => 'integer',
        ];
    }
}
