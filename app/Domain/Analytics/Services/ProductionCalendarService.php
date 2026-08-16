<?php

namespace App\Domain\Analytics\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Plant;
use App\Models\ProductionCalendarDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * El calendario de producción: cuántas horas la planta *debía* moler cada día.
 *
 * Es el denominador de la eficiencia. Sin él, Fronda solo puede hablar de
 * disponibilidad de máquinas —otra afirmación, más pobre— y el número que la
 * gerencia pide no existe.
 *
 * Cargarlo día por día a mano es lo que hace que un CMMS se abandone: nadie va a
 * teclear 31 filas cada mes. Por eso no se entra por el día, sino por el período:
 * {@see programMonth()} siembra la jornada del mes entero, y {@see upsertWeek()} es
 * la puerta que usa la planta cada semana, cuando ya sabe cuánta fruta entró.
 *
 * Las dos escriben lo mismo —una fila por día— porque el día es la unidad de la que
 * cuelgan la eficiencia, el MTBF y el cierre mensual. La semana y el mes son formas
 * de teclear, no entidades de este dominio.
 */
class ProductionCalendarService
{
    /**
     * Programa un mes completo con una jornada fija por día.
     *
     * Los días que ya tienen horas registradas **no se tocan** salvo que se pida
     * explícitamente: el planificador que ya corrigió un domingo no puede perder esa
     * corrección por volver a cargar el mes. Los domingos —o el día de descanso que
     * la planta use— se programan en cero, que es un dato legítimo: un día que nunca
     * debía producir no es un día malo.
     *
     * @param  list<int>  $restDays  días de la semana sin molienda (1 = lunes … 7 = domingo)
     * @return array{created: int, updated: int, skipped: int}
     *
     * @throws BusinessRuleException
     */
    public function programMonth(
        Plant $plant,
        int $year,
        int $month,
        float $hoursPerDay,
        array $restDays = [],
        bool $overwriteExisting = false,
    ): array {
        if ($hoursPerDay < 0 || $hoursPerDay > 24) {
            throw new BusinessRuleException('Un día no tiene más de 24 horas de molienda.');
        }

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $existing = ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (ProductionCalendarDay $day): string => $day->calendar_date->toDateString());

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $plant, $start, $end, $hoursPerDay, $restDays, $overwriteExisting,
            $existing, &$created, &$updated, &$skipped
        ): void {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $key = $date->toDateString();
                $hours = in_array($date->isoWeekday(), $restDays, strict: true) ? 0.0 : $hoursPerDay;

                $row = $existing->get($key);

                if ($row !== null) {
                    if (! $overwriteExisting) {
                        $skipped++;

                        continue;
                    }

                    $row->update(['programmed_hours' => $hours]);
                    $updated++;

                    continue;
                }

                ProductionCalendarDay::withoutGlobalScopes()->create([
                    'tenant_id' => $plant->tenant_id,
                    'plant_id' => $plant->id,
                    'calendar_date' => $key,
                    'programmed_hours' => $hours,
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Escribe una semana entera, día por día, de una sola vez.
     *
     * La semana no es una entidad de este dominio: es la forma en que el planificador
     * teclea. Por dentro sigue siendo una fila por día —la misma que alimenta la
     * eficiencia, el MTBF y el cierre mensual— y por eso una semana a caballo entre
     * dos meses reparte sus días en el mes que a cada uno le toca, sin que el cierre
     * mensual tenga que enterarse de que existen las semanas.
     *
     * Las horas y las toneladas se escriben juntas, por el mismo motivo que en la
     * tabla: la planta cierra el día una sola vez, y separarlas garantizaba que una de
     * las dos se quedara sin llenar.
     *
     * Un día con horas en `null` **no se escribe**. Cero y «no sé» no son lo mismo: un
     * domingo programado en cero es un dato legítimo que baja el denominador, y un día
     * sin fila es un día del que no sabemos nada — {@see programmedHours()} depende de
     * esa distinción para decidir si el mes tiene indicador.
     *
     * @param  array<string, array{programmed_hours?: float|string|null, processed_tons?: float|string|null}>  $days
     * @return array{created: int, updated: int, skipped: int}
     *
     * @throws BusinessRuleException
     */
    public function upsertWeek(Plant $plant, Carbon $weekStart, array $days): array
    {
        $start = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        $writable = [];

        foreach ($days as $date => $values) {
            $day = Carbon::parse($date)->startOfDay();

            if ($day->lt($start) || $day->gt($end)) {
                throw new BusinessRuleException(
                    "El día {$day->toDateString()} no pertenece a la semana del {$start->toDateString()}."
                );
            }

            $hours = $values['programmed_hours'] ?? null;

            if ($hours === null || $hours === '') {
                continue;
            }

            $hours = (float) $hours;

            if ($hours < 0 || $hours > 24) {
                throw new BusinessRuleException('Un día no tiene más de 24 horas de molienda.');
            }

            $tons = (float) ($values['processed_tons'] ?? 0);

            if ($tons < 0) {
                throw new BusinessRuleException('La fruta procesada no puede ser negativa.');
            }

            $writable[$day->toDateString()] = [
                'programmed_hours' => $hours,
                'processed_tons' => $tons,
            ];
        }

        $existing = ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (ProductionCalendarDay $day): string => $day->calendar_date->toDateString());

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($plant, $writable, $existing, &$created, &$updated): void {
            foreach ($writable as $date => $values) {
                $row = $existing->get($date);

                if ($row !== null) {
                    $row->update($values);
                    $updated++;

                    continue;
                }

                ProductionCalendarDay::withoutGlobalScopes()->create([
                    'tenant_id' => $plant->tenant_id,
                    'plant_id' => $plant->id,
                    'calendar_date' => $date,
                    ...$values,
                ]);
                $created++;
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => count($days) - count($writable),
        ];
    }

    /**
     * Los siete días de una semana, con lo que ya esté escrito.
     *
     * Devuelve siempre las siete claves, exista o no la fila: la pantalla de captura
     * tiene que pintar el lunes vacío igual que el martes lleno, y el `null` es lo que
     * la deja distinguir un día sin cargar de un día cargado en cero.
     *
     * @return array<string, array{programmed_hours: float|null, processed_tons: float|null, notes: string|null}>
     */
    public function week(Plant $plant, Carbon $weekStart): array
    {
        $start = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        $existing = ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (ProductionCalendarDay $day): string => $day->calendar_date->toDateString());

        $week = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $existing->get($key);

            $week[$key] = [
                'programmed_hours' => $row?->programmed_hours,
                'processed_tons' => $row?->processed_tons,
                'notes' => $row?->notes,
            ];
        }

        return $week;
    }

    /**
     * Horas programadas del mes. `null` —no cero— cuando el mes no tiene un solo día
     * cargado: un mes sin calendario no es un mes de cero horas, es un mes del que no
     * sabemos nada, y la diferencia decide si la eficiencia se puede calcular.
     */
    public function programmedHours(Plant $plant, int $year, int $month): ?float
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $days = ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [$start->toDateString(), $end->toDateString()]);

        if (! $days->exists()) {
            return null;
        }

        return round((float) $days->sum('programmed_hours'), 2);
    }
}
