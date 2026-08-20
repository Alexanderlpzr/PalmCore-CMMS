<?php

namespace App\Domain\Energy\Services;

use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Las lecturas de los contadores de energía.
 *
 * La aritmética es la de {@see EquipmentMeterReadingService}
 * —delta contra la lectura anterior, y en un reset todo lo que marca el contador nuevo
 * es consumo— porque el problema es idéntico y aquella ya está probada. Lo que **no** se
 * copia son las dos llamadas que aquella hace al cerrar la transacción: sincronizar las
 * horas de vida de los componentes y resolver la alerta de horómetro mudo. Un kWh no es
 * una hora de vida de nada.
 *
 * Diferencia de fondo con el original: aquí no hay columna cache del acumulado en otra
 * tabla. El acumulado sale de la lectura anterior, así que no puede desincronizarse.
 */
class EnergyMeterReadingService
{
    /**
     * Registra la lectura de un día. Si ese día ya tenía lectura, la corrige.
     *
     * La cadena posterior se recalcula solo cuando hace falta: rellenar un día olvidado
     * mueve el delta de todos los días siguientes, y dejarlos como estaban sería
     * exactamente el error que traía la hoja de cálculo.
     */
    public function record(
        EnergyMeter $meter,
        float $readingValue,
        User $recordedBy,
        Carbon $readingDate,
        ?string $notes = null,
    ): EnergyMeterReading {
        if ($readingValue < 0) {
            throw new \InvalidArgumentException('La lectura de un contador no puede ser negativa.');
        }

        return DB::transaction(function () use ($meter, $readingValue, $recordedBy, $readingDate, $notes): EnergyMeterReading {
            $date = $readingDate->toDateString();

            $previousRow = $this->readingBefore($meter, $date);
            $previous = $previousRow?->reading_value;

            $isReset = $previous !== null && $readingValue < $previous;

            // Con el contador reemplazado, el dial nuevo arrancó en cero: todo lo que
            // marca es consumo desde el cambio. La primera lectura de todas no es
            // consumo, es la línea base contra la que se medirá la siguiente.
            $delta = match (true) {
                $previous === null => 0.0,
                $isReset => $readingValue,
                default => $readingValue - $previous,
            };

            $accumulated = round((float) ($previousRow?->accumulated_value ?? 0) + $delta, 1);

            $reading = EnergyMeterReading::updateOrCreate(
                ['energy_meter_id' => $meter->id, 'reading_date' => $date],
                [
                    'tenant_id' => $meter->tenant_id,
                    'reading_value' => $readingValue,
                    'previous_value' => $previous,
                    'delta' => round($delta, 1),
                    'accumulated_value' => $accumulated,
                    'is_reset' => $isReset,
                    'recorded_by' => $recordedBy->id,
                    'notes' => $notes,
                ],
            );

            if ($this->hasReadingsAfter($meter, $date)) {
                $this->recomputeChain($meter);

                return $reading->fresh();
            }

            return $reading;
        });
    }

    /**
     * Borra una lectura y deja la cadena consistente.
     */
    public function deleteReading(EnergyMeterReading $reading): void
    {
        DB::transaction(function () use ($reading): void {
            $meter = $reading->energyMeter;
            $reading->delete();
            $this->recomputeChain($meter);
        });
    }

    /**
     * Reconstruye delta y acumulado de toda la serie, en orden de fecha.
     *
     * El acumulado arranca donde estaba antes de la primera lectura: el contador pudo
     * llevar años girando antes de que alguien empezara a anotarlo, y ese punto de
     * partida es el acumulado de la primera lectura menos su propio delta.
     */
    public function recomputeChain(EnergyMeter $meter): void
    {
        $readings = EnergyMeterReading::withoutGlobalScopes()
            ->where('energy_meter_id', $meter->id)
            ->orderBy('reading_date')
            ->orderBy('id')
            ->get();

        if ($readings->isEmpty()) {
            return;
        }

        $first = $readings->first();
        $accumulated = (float) $first->accumulated_value - (float) $first->delta;

        $previous = null;

        foreach ($readings as $reading) {
            $value = (float) $reading->reading_value;

            $isReset = $previous !== null && $value < $previous;
            $delta = match (true) {
                $previous === null => 0.0,
                $isReset => $value,
                default => $value - $previous,
            };

            $accumulated = round($accumulated + $delta, 1);

            $reading->update([
                'previous_value' => $previous,
                'delta' => round($delta, 1),
                'accumulated_value' => $accumulated,
                'is_reset' => $isReset,
            ]);

            $previous = $value;
        }
    }

    /**
     * El consumo de un rango, sumando deltas.
     *
     * Es la suma de los deltas y no la resta de los extremos a propósito: si el contador
     * se reemplazó dentro del rango, la resta daría un número negativo o absurdo, y el
     * delta ya trae resuelto ese caso.
     */
    public function consumptionBetween(EnergyMeter $meter, Carbon $from, Carbon $to): float
    {
        return round((float) EnergyMeterReading::withoutGlobalScopes()
            ->where('energy_meter_id', $meter->id)
            ->whereBetween('reading_date', [$from->toDateString(), $to->toDateString()])
            ->sum('delta'), 1);
    }

    private function readingBefore(EnergyMeter $meter, string $date): ?EnergyMeterReading
    {
        return EnergyMeterReading::withoutGlobalScopes()
            ->where('energy_meter_id', $meter->id)
            ->where('reading_date', '<', $date)
            ->orderByDesc('reading_date')
            ->first();
    }

    private function hasReadingsAfter(EnergyMeter $meter, string $date): bool
    {
        return EnergyMeterReading::withoutGlobalScopes()
            ->where('energy_meter_id', $meter->id)
            ->where('reading_date', '>', $date)
            ->exists();
    }
}
