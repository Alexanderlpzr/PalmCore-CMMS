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
 * {@see programMonth()} siembra la jornada del mes entero, y {@see upsertDay()} es la
 * puerta que usa la planta cada día, cuando ya sabe cuánta fruta entró. Se pasó de la
 * semana al día porque esperar al domingo dejaba a la planta sin saber por dónde iba su
 * RFF a mitad de mes.
 *
 * Las dos escriben lo mismo —una fila por día— porque el día es la unidad de la que
 * cuelgan la eficiencia, el MTBF y el cierre mensual. El mes es una forma de teclear,
 * no una entidad de este dominio.
 */
class ProductionCalendarService
{
    /**
     * Techo de cordura para la fruta de un día, en toneladas.
     *
     * No es la capacidad de ninguna planta concreta: es el orden de magnitud a partir del
     * cual la cifra ya no puede ser toneladas. El Pajuil hace unas 250 t en un buen día.
     */
    private const MAX_DAILY_TONS = 2000;

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
     * Escribe la jornada de un día.
     *
     * Es la puerta que usa la planta: se cierra el día cuando se cierra, y el RFF del mes
     * se ve crecer en la tabla de abajo. Antes se tecleaba la semana entera de una vez, y
     * eso obligaba a esperar al domingo para saber por dónde iba la fruta.
     *
     * Horas en `null` no escribe nada. Cero y «no sé» no son lo mismo: un domingo
     * programado en cero baja el denominador de la eficiencia, y un día sin fila es un día
     * del que no sabemos nada.
     *
     * @throws BusinessRuleException
     */
    public function upsertDay(Plant $plant, Carbon $date, float|string|null $hours, float|string|null $tons): ?ProductionCalendarDay
    {
        if ($hours === null || $hours === '') {
            return null;
        }

        [$hours, $tons] = $this->validated($date, $hours, $tons);

        return DB::transaction(fn (): ProductionCalendarDay => ProductionCalendarDay::withoutGlobalScopes()
            ->updateOrCreate(
                ['plant_id' => $plant->id, 'calendar_date' => $date->toDateString()],
                [
                    'tenant_id' => $plant->tenant_id,
                    'programmed_hours' => $hours,
                    'processed_tons' => $tons,
                ],
            ));
    }

    /**
     * El mes día a día, con el RFF acumulándose.
     *
     * La columna del acumulado es la razón de ser de esta tabla: la planta necesita saber
     * por dónde va la fruta del mes sin esperar al cierre ni sumar a mano. Se acumula solo
     * sobre los días que tienen fila — un día sin cargar no arrastra el total ni lo
     * congela, simplemente no aporta.
     *
     * Los días futuros no se pintan: una jornada que no ha ocurrido no tiene nada que
     * mostrar y solo alarga la tabla.
     *
     * @return array{
     *     days: list<array{date: string, label: string, hours: ?float, tons: ?float, accumulated_tons: ?float, notes: ?string}>,
     *     total_hours: float,
     *     total_tons: float,
     * }
     */
    public function month(Plant $plant, Carbon $anyDayOfMonth): array
    {
        $start = $anyDayOfMonth->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $last = $end->isFuture() ? Carbon::today() : $end;

        $existing = ProductionCalendarDay::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->whereBetween('calendar_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (ProductionCalendarDay $d): string => $d->calendar_date->toDateString());

        $days = [];
        $acumulado = 0.0;
        $totalHoras = 0.0;

        for ($date = $start->copy(); $date->lte($last); $date->addDay()) {
            $key = $date->toDateString();
            $row = $existing->get($key);

            if ($row !== null) {
                $acumulado += (float) $row->processed_tons;
                $totalHoras += (float) $row->programmed_hours;
            }

            $days[] = [
                'date' => $key,
                'label' => $date->translatedFormat('D d'),
                'hours' => $row?->programmed_hours,
                'tons' => $row?->processed_tons,
                // El acumulado solo tiene sentido donde hay dato: en un día sin cargar
                // repetiría el número anterior y parecería que ese día produjo cero.
                'accumulated_tons' => $row === null ? null : round($acumulado, 2),
                'notes' => $row?->notes,
            ];
        }

        return [
            'days' => $days,
            'total_hours' => round($totalHoras, 2),
            'total_tons' => round($acumulado, 2),
        ];
    }

    /**
     * Las dos cifras de una jornada, validadas.
     *
     * @return array{0: float, 1: float}
     *
     * @throws BusinessRuleException
     */
    private function validated(Carbon $day, float|string $hours, float|string|null $tons): array
    {
        $hours = (float) $hours;

        if ($hours < 0 || $hours > 24) {
            throw new BusinessRuleException('Un día no tiene más de 24 horas de molienda.');
        }

        $tons = (float) ($tons ?? 0);

        if ($tons < 0) {
            throw new BusinessRuleException('La fruta procesada no puede ser negativa.');
        }

        // El tope de unidad. Un mes entero se cargó una vez en kilogramos y entró sin
        // protestar: la productividad y el kWh por tonelada salieron mil veces inflados y
        // nadie lo notó hasta cruzarlos contra el consumo eléctrico.
        if ($tons > self::MAX_DAILY_TONS) {
            throw new BusinessRuleException(
                "El día {$day->toDateString()} trae {$tons} t, muy por encima de lo que "
                .'una planta puede prensar en un día. ¿Están en kilogramos?'
            );
        }

        return [$hours, $tons];
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
