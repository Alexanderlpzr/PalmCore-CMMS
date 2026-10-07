<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * El auxilio de alimentación de El Pajuil: una suma fija por cada comida cuya franja el
 * trabajador «cobija», es decir, en la que estuvo trabajando en algún momento.
 *
 *  - Turno de día: desayuno (5:20–7:20), almuerzo (11:20–1:20) y merienda (5:30–6:30 p. m.).
 *  - Turno de noche: cena (5:30–6:30 p. m.), merienda nocturna (8:30–9:30 p. m.) y desayuno
 *    si sale entre las 5:30 y las 6:30 a. m. del día siguiente.
 *
 * El turno lo decide la hora de entrada: desde la 1 p. m. (o antes de las 4 a. m.) es de
 * noche, y así el nocturno que sale a las 6 a. m. no cobra también el desayuno de día.
 * Solo cuentan los días confirmados en «Horas por confirmar», con las horas de sus marcas
 * en la puerta; cada comida se cuenta una vez por día aunque haya varias marcas.
 *
 * Es un acuerdo entre los trabajadores y la empresa que se paga aparte: no entra a la
 * nómina, al IBC ni a las prestaciones. El valor y las franjas son parámetros con vigencia.
 */
class MealAllowanceCalculator
{
    /** Las comidas, con su nombre en plural para las columnas. */
    public const MEALS = [
        'desayuno' => 'Desayunos',
        'almuerzo' => 'Almuerzos',
        'merienda' => 'Meriendas',
        'cena' => 'Cenas',
        'merienda_nocturna' => 'Meriendas nocturnas',
    ];

    public function __construct(private readonly PayrollParameterService $parameters) {}

    /**
     * @param  list<string>  $employeeIds
     * @return array<string, array{counts: array<string, int>, meals: int, amount: float, days: array<string, list<string>>}>
     */
    public function forEmployees(array $employeeIds, string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $from = CarbonImmutable::parse(CarbonImmutable::instance($from)->toDateString());
        $to = CarbonImmutable::parse(CarbonImmutable::instance($to)->toDateString());
        $timezone = Tenant::withoutGlobalScopes()->find($tenantId)?->plantTimezone() ?? 'America/Bogota';

        $confirmed = AttendanceDay::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->between($from->toDateString(), $to->toDateString())
            ->confirmed()
            ->get(['employee_id', 'work_date'])
            ->groupBy('employee_id')
            ->map(fn ($days) => $days->mapWithKeys(fn (AttendanceDay $d): array => [$d->work_date->toDateString() => true])->all());

        // Un día de margen a cada lado: el turno de noche del 26 sale el 27.
        $scans = AttendanceScan::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $confirmed->keys()->all())
            ->whereBetween('scanned_at', [
                CarbonImmutable::parse($from->toDateString(), $timezone)->subDay()->utc(),
                CarbonImmutable::parse($to->toDateString(), $timezone)->addDays(2)->utc(),
            ])
            ->orderBy('scanned_at')
            ->get()
            ->groupBy('employee_id');

        $result = [];

        foreach ($confirmed as $employeeId => $days) {
            $mealsByDay = [];

            foreach ($this->sessions($scans[$employeeId] ?? collect(), $timezone) as [$entry, $exit]) {
                $date = $entry->toDateString();

                if (! isset($days[$date])) {
                    continue;
                }

                $mealsByDay[$date] = array_values(array_unique([
                    ...($mealsByDay[$date] ?? []),
                    ...$this->mealsOf($entry, $exit, $tenantId),
                ]));
            }

            ksort($mealsByDay);
            $counts = array_fill_keys(array_keys(self::MEALS), 0);
            $amount = 0.0;

            foreach ($mealsByDay as $date => $meals) {
                $value = $this->value(PayrollParameter::MealAllowanceValue, CarbonImmutable::parse($date), $tenantId);

                foreach ($meals as $meal) {
                    $counts[$meal]++;
                    $amount += $value;
                }
            }

            $result[$employeeId] = [
                'counts' => $counts,
                'meals' => array_sum($counts),
                'amount' => round($amount, 2),
                'days' => array_filter($mealsByDay),
            ];
        }

        return $result;
    }

    /**
     * Las comidas cuya franja toca el turno.
     *
     * @return list<string>
     */
    public function mealsOf(CarbonImmutable $entry, CarbonImmutable $exit, string $tenantId): array
    {
        $day = $entry->startOfDay();
        $nightFrom = $this->value(PayrollParameter::MealNightShiftFrom, $day, $tenantId);
        $hour = $entry->hour + $entry->minute / 60;
        $isNight = $hour >= $nightFrom || $hour < 4;
        // El nocturno que entró pasada la medianoche pertenece a la noche anterior.
        $base = $isNight && $hour < 4 ? $day->subDay() : $day;

        $windows = $isNight
            ? [
                'cena' => [$base, PayrollParameter::MealDinnerStart, PayrollParameter::MealDinnerEnd],
                'merienda_nocturna' => [$base, PayrollParameter::MealNightSnackStart, PayrollParameter::MealNightSnackEnd],
                'desayuno' => [$base->addDay(), PayrollParameter::MealNightBreakfastStart, PayrollParameter::MealNightBreakfastEnd],
            ]
            : [
                'desayuno' => [$base, PayrollParameter::MealBreakfastStart, PayrollParameter::MealBreakfastEnd],
                'almuerzo' => [$base, PayrollParameter::MealLunchStart, PayrollParameter::MealLunchEnd],
                'merienda' => [$base, PayrollParameter::MealSnackStart, PayrollParameter::MealSnackEnd],
            ];

        $meals = [];

        foreach ($windows as $meal => [$date, $startKey, $endKey]) {
            $start = $date->addMinutes((int) round($this->value($startKey, $day, $tenantId) * 60));
            $end = $date->addMinutes((int) round($this->value($endKey, $day, $tenantId) * 60));

            // «Cobijar»: estar trabajando en algún momento de la franja.
            if ($entry->lessThan($end) && $exit->greaterThan($start)) {
                $meals[] = $meal;
            }
        }

        return $meals;
    }

    /**
     * Entradas emparejadas con su salida, en la hora de la planta. Una entrada sin salida
     * no es un turno: no se sabe hasta cuándo trabajó.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function sessions(iterable $scans, string $timezone): array
    {
        $sessions = [];
        $open = null;

        foreach ($scans as $scan) {
            $at = CarbonImmutable::instance($scan->scanned_at)->setTimezone($timezone);

            if ($scan->direction === AttendanceDirection::Entrada) {
                $open = $at;

                continue;
            }

            if ($open) {
                $sessions[] = [$open, $at];
                $open = null;
            }
        }

        return $sessions;
    }

    private function value(PayrollParameter $parameter, CarbonImmutable $on, string $tenantId): float
    {
        return $this->parameters->valueOrDefault($parameter, $on, $tenantId, $parameter->seedValue());
    }
}
