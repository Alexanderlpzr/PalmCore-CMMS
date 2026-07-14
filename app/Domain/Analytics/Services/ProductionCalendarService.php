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
 * teclear 31 filas cada mes. Por eso la puerta principal es {@see programMonth()},
 * que llena el mes de una vez y respeta lo que ya estaba escrito.
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
