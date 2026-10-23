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
 * El auxilio de alimentación de El Pajuil: una suma por cada comida cuya franja el
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

    /** Las comidas que se pagan con el valor de merienda. */
    public const SNACKS = ['merienda', 'merienda_nocturna'];

    /** @var array<string, list<array{0: string, 1: int, 2: int}>> las franjas por día, ya calculadas */
    private array $windows = [];

    public function __construct(private readonly PayrollParameterService $parameters) {}

    /**
     * Con `$includeUnconfirmed` es una estimación, la del tablero de costo de personal:
     * cuentan también los días sin confirmar y, si se da `$openUntil`, el turno que sigue
     * abierto se toma como si terminara a esa hora.
     *
     * @param  list<string>  $employeeIds
     * @return array<string, array{counts: array<string, int>, meals: int, amount: float, days: array<string, list<string>>, shifts: array<string, array{0: CarbonImmutable, 1: CarbonImmutable}>}>
     */
    public function forEmployees(array $employeeIds, string $tenantId, CarbonInterface $from, CarbonInterface $to, bool $includeUnconfirmed = false, ?CarbonInterface $openUntil = null): array
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
            ->unless($includeUnconfirmed, fn ($query) => $query->confirmed())
            ->get(['employee_id', 'work_date'])
            ->groupBy('employee_id')
            ->map(fn ($days) => $days->mapWithKeys(fn (AttendanceDay $d): array => [$d->work_date->toDateString() => true])->all());

        // En la estimación cuenta todo turno del rango, aunque el día no se haya armado
        // todavía: el que sigue adentro no tiene día hasta que marque la salida.
        if ($includeUnconfirmed) {
            $confirmed = collect(array_fill_keys($employeeIds, []))->replace($confirmed->all());
        }

        $openUntil = $openUntil ? CarbonImmutable::instance($openUntil)->setTimezone($timezone) : null;

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
            $shifts = [];

            foreach ($this->sessions($scans[$employeeId] ?? collect(), $timezone, $openUntil) as [$entry, $exit]) {
                $date = $entry->toDateString();

                if ($includeUnconfirmed ? ($date < $from->toDateString() || $date > $to->toDateString()) : ! isset($days[$date])) {
                    continue;
                }

                // La primera entrada y la última salida del día, para el detalle.
                $shifts[$date] = [
                    isset($shifts[$date]) ? $shifts[$date][0]->min($entry) : $entry,
                    isset($shifts[$date]) ? $shifts[$date][1]->max($exit) : $exit,
                ];

                $mealsByDay[$date] = array_values(array_unique([
                    ...($mealsByDay[$date] ?? []),
                    ...$this->mealsOf($entry, $exit, $tenantId),
                ]));
            }

            ksort($mealsByDay);
            $counts = array_fill_keys(array_keys(self::MEALS), 0);
            $amount = 0.0;

            foreach ($mealsByDay as $date => $meals) {
                foreach ($meals as $meal) {
                    $counts[$meal]++;
                    $amount += $this->priceOf($meal, CarbonImmutable::parse($date), $tenantId);
                }
            }

            $result[$employeeId] = [
                'counts' => $counts,
                'meals' => array_sum($counts),
                'amount' => round($amount, 2),
                'days' => array_filter($mealsByDay),
                'shifts' => $shifts,
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
        $nightBefore = $isNight && $hour < 4;
        $entryAt = $entry->getTimestamp();
        $exitAt = $exit->getTimestamp();
        $meals = [];

        foreach ($this->windows($day, $isNight, $nightBefore, $tenantId) as [$meal, $start, $end]) {
            // «Cobijar»: estar trabajando en algún momento de la franja.
            if ($entryAt < $end && $exitAt > $start) {
                $meals[] = $meal;
            }
        }

        return $meals;
    }

    /**
     * Las franjas de comida de un turno que empieza ese día, como instantes. Son las mismas
     * para todos los que trabajan ese día: se calculan una vez y no por cada turno —un
     * corte trae más de mil—.
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function windows(CarbonImmutable $day, bool $isNight, bool $nightBefore, string $tenantId): array
    {
        $key = $tenantId.'|'.$day->toDateString().'|'.(int) $isNight.'|'.(int) $nightBefore;

        if (isset($this->windows[$key])) {
            return $this->windows[$key];
        }

        $base = $nightBefore ? $day->subDay() : $day;
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

        $result = [];

        foreach ($windows as $meal => [$date, $startKey, $endKey]) {
            $result[] = [
                $meal,
                $date->addMinutes((int) round($this->value($startKey, $day, $tenantId) * 60))->getTimestamp(),
                $date->addMinutes((int) round($this->value($endKey, $day, $tenantId) * 60))->getTimestamp(),
            ];
        }

        return $this->windows[$key] = $result;
    }

    /**
     * Entradas emparejadas con su salida, en la hora de la planta. Una entrada sin salida
     * no es un turno: no se sabe hasta cuándo trabajó. Salvo con `$openUntil`: la última
     * entrada, si es de las últimas horas, se toma como un turno que llega hasta ahí.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function sessions(iterable $scans, string $timezone, ?CarbonImmutable $openUntil = null): array
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

        if ($open && $openUntil && $open->lessThan($openUntil) && $open->diffInHours($openUntil) < AttendanceService::MAX_OPEN_SHIFT_HOURS) {
            $sessions[] = [$open, $openUntil];
        }

        return $sessions;
    }

    /**
     * Lo que vale una comida ese día: las meriendas (de día y de noche) tienen su propio
     * valor; desayuno, almuerzo y cena, el de comida principal.
     */
    public function priceOf(string $meal, CarbonImmutable $on, string $tenantId): float
    {
        return $this->value(in_array($meal, self::SNACKS, true) ? PayrollParameter::MealSnackValue : PayrollParameter::MealAllowanceValue, $on, $tenantId);
    }

    private function value(PayrollParameter $parameter, CarbonImmutable $on, string $tenantId): float
    {
        return $this->parameters->valueOrDefault($parameter, $on, $tenantId, $parameter->seedValue());
    }
}
