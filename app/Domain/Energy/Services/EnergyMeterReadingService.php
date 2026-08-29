<?php

namespace App\Domain\Energy\Services;

use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Exceptions\BusinessRuleException;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
     * Cuántas veces la mediana histórica puede crecer el consumo de un día antes de que
     * deje de parecer un día raro y empiece a parecer un dedo.
     *
     * Medido contra los datos reales de la planta: la turbina y el generador varían 1,5×
     * entre su día flojo y su día fuerte, pero la red pública llega a 6,6× porque es la
     * fuente de respaldo y se usa a saltos. Quince deja esa variación real muy holgada, y
     * sigue atrapando un dígito de más, que multiplica el consumo por miles.
     */
    private const IMPLAUSIBLE_DELTA_FACTOR = 15;

    /**
     * Cuántas lecturas hacen falta antes de opinar sobre lo que es normal.
     *
     * Con dos días no hay «lo habitual» de nada, y rechazar la tercera lectura de un
     * contador recién puesto en servicio sería un guardia que estorba sin proteger.
     */
    private const MIN_HISTORY_FOR_PLAUSIBILITY = 4;

    /**
     * Registra la lectura de un día. Si ese día ya tenía lectura, la corrige.
     *
     * La cadena posterior se recalcula solo cuando hace falta: rellenar un día olvidado
     * mueve el delta de todos los días siguientes, y dejarlos como estaban sería
     * exactamente el error que traía la hoja de cálculo.
     *
     * `$force` salta la comprobación de plausibilidad. Existe porque el guardia se puede
     * equivocar —un mes de parada seguido de un arranque puede dar un salto legítimo— y
     * un sistema que no deja registrar lo que de verdad pasó acaba siendo el sistema que
     * nadie usa. Pero por defecto protege, y saltárselo es un acto deliberado.
     */
    public function record(
        EnergyMeter $meter,
        float $readingValue,
        User $recordedBy,
        Carbon $readingDate,
        ?string $notes = null,
        bool $force = false,
    ): EnergyMeterReading {
        if ($readingValue < 0) {
            throw new \InvalidArgumentException('La lectura de un contador no puede ser negativa.');
        }

        if (! $force && ($aviso = $this->implausibilityWarning($meter, $readingValue, $readingDate)) !== null) {
            throw new BusinessRuleException($aviso);
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

    /**
     * El mes entero, día a día y contador a contador, listo para pintar.
     *
     * Es la mitad de abajo de la hoja que este módulo reemplazó: una fila por día con el
     * acumulado y el consumo de cada contador. Al pasar al sistema se quedó sin
     * equivalente —la pantalla mostraba un solo día— y para revisar el mes había que
     * navegar de uno en uno.
     *
     * Un día sin lectura entra como `null`, no como cero. La hoja rellenaba ceros y por
     * eso sus días futuros parecían días de consumo nulo; aquí el guion no afirma nada.
     *
     * Una sola consulta para el mes y el resto en memoria: una por celda serían más de
     * noventa en una pantalla que se recarga a cada tecla.
     *
     * @param  Collection<int, EnergyMeter>  $meters
     * @return array{
     *     days: list<array{date: string, label: string, cells: array<string, array{accumulated: ?float, delta: ?float, is_reset: bool}>}>,
     *     totals: array<string, float>,
     * }
     */
    public function monthReadings($meters, Carbon $anyDayOfMonth): array
    {
        $start = $anyDayOfMonth->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        // Sin días futuros: un contador no se lee por adelantado, y pintarlos vacíos
        // alarga la tabla con filas que nunca van a tener nada.
        $last = $end->isFuture() ? Carbon::today() : $end;

        $ids = $meters->pluck('id')->all();

        $porDiaYContador = $ids === [] ? collect() : EnergyMeterReading::withoutGlobalScopes()
            ->whereIn('energy_meter_id', $ids)
            ->whereBetween('reading_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy([
                fn (EnergyMeterReading $r): string => $r->reading_date->toDateString(),
                fn (EnergyMeterReading $r): string => $r->energy_meter_id,
            ]);

        $days = [];
        $totals = array_fill_keys($ids, 0.0);

        for ($date = $start->copy(); $date->lte($last); $date->addDay()) {
            $key = $date->toDateString();
            $cells = [];

            foreach ($meters as $meter) {
                $reading = $porDiaYContador->get($key)?->get($meter->id)?->first();

                $cells[$meter->id] = [
                    'accumulated' => $reading?->reading_value,
                    'delta' => $reading?->delta,
                    'is_reset' => (bool) $reading?->is_reset,
                ];

                $totals[$meter->id] += (float) ($reading?->delta ?? 0);
            }

            $days[] = [
                'date' => $key,
                'label' => $date->translatedFormat('D d'),
                'cells' => $cells,
            ];
        }

        return [
            'days' => $days,
            'totals' => array_map(fn (float $v): float => round($v, 1), $totals),
        ];
    }

    /**
     * El aviso cuando el consumo de un día se sale de lo que ese contador acostumbra, o
     * `null` si la lectura es creíble.
     *
     * Existe por un modo de fallo concreto que el tope absoluto no cubre: un contador
     * acumulado crece sin límite, así que no hay número máximo que ponerle. Pero un
     * dígito de más —24.637.790 en vez de 2.463.979— convierte un día de 5.000 kWh en uno
     * de 22 millones, y al día siguiente la lectura correcta se lee como contador
     * reemplazado y vuelve a contar entera. Dos meses arruinados por una tecla.
     *
     * Se mide contra la mediana y no contra el promedio a propósito: si una lectura mala
     * ya entró, el promedio se va con ella y el guardia deja de avisar justo cuando más
     * falta hace. La mediana aguanta.
     *
     * Solo mira hacia arriba. Un consumo anormalmente bajo es un día de planta parada,
     * que es información legítima y frecuente.
     */
    public function implausibilityWarning(EnergyMeter $meter, float $readingValue, Carbon $readingDate): ?string
    {
        $date = $readingDate->toDateString();
        $previousRow = $this->readingBefore($meter, $date);

        // Sin lectura previa no hay consumo que juzgar, y un contador que bajó es un
        // reemplazo: ese caso ya lo resuelve el reset.
        if ($previousRow === null || $readingValue < (float) $previousRow->reading_value) {
            return null;
        }

        $delta = $readingValue - (float) $previousRow->reading_value;
        $tipico = $this->typicalDelta($meter, $date);

        if ($tipico === null || $delta <= $tipico * self::IMPLAUSIBLE_DELTA_FACTOR) {
            return null;
        }

        return sprintf(
            '%s: %s kWh en un día, cuando lo habitual en este contador son unos %s. '
            .'Revisa que no sobre un dígito. Si la lectura es correcta, confírmala.',
            $meter->name,
            number_format($delta, 0, ',', '.'),
            number_format($tipico, 0, ',', '.'),
        );
    }

    /**
     * La mediana de los consumos con movimiento de este contador.
     *
     * Los días en cero se descartan: un domingo parado no dice nada sobre cuánto consume
     * la planta cuando trabaja, y dejarlos dentro hundiría la mediana hasta hacer
     * sospechoso cualquier día normal.
     */
    private function typicalDelta(EnergyMeter $meter, string $date): ?float
    {
        $deltas = EnergyMeterReading::withoutGlobalScopes()
            ->where('energy_meter_id', $meter->id)
            ->where('reading_date', '<', $date)
            ->where('delta', '>', 0)
            ->orderByDesc('reading_date')
            ->limit(60)
            ->pluck('delta')
            ->map(fn ($v): float => (float) $v)
            ->sort()
            ->values();

        if ($deltas->count() < self::MIN_HISTORY_FOR_PLAUSIBILITY) {
            return null;
        }

        $medio = intdiv($deltas->count(), 2);

        return $deltas->count() % 2 === 1
            ? $deltas[$medio]
            : round(($deltas[$medio - 1] + $deltas[$medio]) / 2, 1);
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
