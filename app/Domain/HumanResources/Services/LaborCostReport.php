<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Enums\NoveltyDayBasis;
use App\Domain\HumanResources\Enums\NoveltyType;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Domain\HumanResources\Support\EmployeeProfileOptions;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\Tenant;
use App\Support\FrondaPalette;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Cuánto cuesta el personal de la extractora en un rango de días: lo que alimenta el
 * tablero «Costo de personal».
 *
 * Tres conceptos, decididos con la empresa el 2026-10-15:
 *
 *  - **Jornal**: salario ÷ días del mes (30) por cada día en que la persona estuvo
 *    contratada, entrara o no, domingos y festivos incluidos: es el costo fijo, el mismo
 *    que liquida la nómina con corte (pedido por la empresa el 2026-10-23; del 15 al 23 de
 *    octubre solo contó el día de quien entraba). El día de novedad sin entrada no es
 *    jornal: va en novedades pagadas, o en nada si no es remunerada —la ausencia no
 *    justificada y el permiso no remunerado descuentan el día—.
 *  - **Recargos y horas extras**: las horas del reloj valoradas como en la nómina,
 *    salario ÷ divisor × factor. Cuentan también las propuestas sin confirmar y el turno
 *    que sigue abierto (hasta ahora), y por eso esas cifras van marcadas «estimado».
 *    Las que la regla del bono pase a bonificación cuestan lo mismo y entran igual.
 *  - **Alimentación**: el auxilio por franja cubierta, con la misma estimación.
 *  - **Novedades pagadas** (2026-10-22): el día de incapacidad, permiso autorizado,
 *    calamidad o vacaciones de quien no vino también se le paga, con el mismo valor que en
 *    la nómina. Al lado va cuánto de eso reembolsan la EPS o la ARL, sin descontarlo: el
 *    tablero muestra lo que sale, y el reembolso es un dato.
 *
 * No incluye prestaciones, aportes patronales ni parafiscales: es el costo directo del día.
 */
class LaborCostReport
{
    /** @var array<string, string> */
    public const CONCEPTS = [
        'jornal' => 'Jornales',
        'novelties' => 'Novedades pagadas',
        'surcharges' => 'Recargos',
        'overtime' => 'Horas extras',
        'meals' => 'Alimentación',
    ];

    /** @var array<string, string> */
    public const CONCEPT_COLORS = [
        'jornal' => FrondaPalette::Petrol[600],
        'novelties' => '#8b5cf6',
        'surcharges' => FrondaPalette::Petrol[300],
        'overtime' => FrondaPalette::Brand[600],
        'meals' => '#d97706',
    ];

    public const NO_AREA = 'sin_area';

    /** Cuánto atrás se buscan incapacidades para seguir la cuenta de sus prórrogas. */
    private const INCAPACITY_LOOKBACK_DAYS = 540;

    /**
     * Un color fijo por área, para que la misma área sea del mismo color en todas las
     * tortas y entre un día y otro.
     *
     * @var array<string, string>
     */
    public const AREA_COLORS = [
        'procesos' => FrondaPalette::Brand[600],
        'mantenimiento' => FrondaPalette::Petrol[600],
        'laboratorio' => FrondaPalette::Petrol[400],
        'calidad' => FrondaPalette::Brand[300],
        'mope' => '#d97706',
        'oficios_varios' => '#94a3b8',
        'administrativo' => '#7c3aed',
        self::NO_AREA => '#cbd5e1',
    ];

    public function __construct(
        private readonly PayrollParameterService $parameters,
        private readonly MealAllowanceCalculator $meals,
        private readonly AttendanceDayBuilder $builder,
    ) {}

    /**
     * @return array{
     *     rows: list<array{employee_id: string, code: ?string, name: string, position: ?string, area: string, area_label: string, days: int, present: int, inside: bool, without_salary: bool, jornal: float, day_value: float, novelties: float, novelty_days: int, reimbursed: float, surcharges: float, surcharge_hours: float, overtime: float, overtime_hours: float, meals: float, meal_count: int, total: float, estimated: bool}>,
     *     areas: array<string, array{label: string, color: string, people: int, jornal: float, novelties: float, surcharges: float, overtime: float, meals: float, total: float}>,
     *     totals: array{people: int, present: int, inside: int, without_salary: int, jornal: float, novelties: float, novelty_days: int, reimbursed: float, surcharges: float, surcharge_hours: float, overtime: float, overtime_hours: float, meals: float, meal_count: int, total: float},
     *     entries: list<array{employee_id: string, name: string, area: string, area_label: string, at: CarbonImmutable, jornal: float, inside: bool}>,
     *     estimated: bool,
     * }
     */
    public function forRange(Tenant $tenant, CarbonInterface $from, CarbonInterface $to, ?CarbonInterface $now = null): array
    {
        $timezone = $tenant->plantTimezone();
        $from = CarbonImmutable::parse(CarbonImmutable::instance($from)->toDateString());
        $to = CarbonImmutable::parse(CarbonImmutable::instance($to)->toDateString());
        $now = CarbonImmutable::instance($now ?? CarbonImmutable::now())->setTimezone($timezone);

        $employees = Employee::query()
            ->forTenant($tenant->id)
            ->where(fn ($query) => $query
                ->where('status', EmploymentStatus::Activo)
                ->orWhereDate('termination_date', '>=', $from->toDateString()))
            ->where(fn ($query) => $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $to->toDateString()))
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($employees as $employee) {
            $rows[$employee->id] = $this->emptyRow($employee) + ['days' => $this->employedDays($employee, $from, $to)];
        }

        $this->addAttendance($rows, $employees, $tenant->id, $from, $to);
        $entries = $this->addEntries($rows, $tenant->id, $from, $to, $timezone);
        $this->addOpenShifts($rows, $employees, $tenant->id, $from, $to, $now);
        $this->addMeals($rows, $tenant->id, $from, $to, $now);
        $this->addNovelties($rows, $employees, $tenant->id, $from, $to);

        $all = collect($rows)->map(function (array $row) use ($employees, $tenant, $from, $to): array {
            $row = $this->chargeNovelties($row, $employees[$row['employee_id']], $tenant->id);
            $row = $this->chargeJornal($row, $employees[$row['employee_id']], $tenant->id, $from, $to);
            $row['total'] = round($row['jornal'] + $row['novelties'] + $row['surcharges'] + $row['overtime'] + $row['meals'], 2);

            return $row;
        });

        // En las tablas y las tortas, solo quien costó algo: el que no tenía contrato no.
        $rows = $all
            ->filter(fn (array $row): bool => $row['present'] > 0 || $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        return [
            'rows' => $rows->all(),
            'areas' => self::byArea($rows),
            'totals' => [
                'people' => $all->filter(fn (array $row): bool => $row['days'] > 0)->count(),
                'present' => $rows->filter(fn (array $row): bool => $row['present'] > 0)->count(),
                'inside' => $rows->where('inside', true)->count(),
                'without_salary' => $rows->where('without_salary', true)->count(),
                'jornal' => round($rows->sum('jornal'), 2),
                'novelties' => round($rows->sum('novelties'), 2),
                'novelty_days' => (int) $rows->sum('novelty_days'),
                'reimbursed' => round($rows->sum('reimbursed'), 2),
                'surcharges' => round($rows->sum('surcharges'), 2),
                'surcharge_hours' => round($rows->sum('surcharge_hours'), 2),
                'overtime' => round($rows->sum('overtime'), 2),
                'overtime_hours' => round($rows->sum('overtime_hours'), 2),
                'meals' => round($rows->sum('meals'), 2),
                'meal_count' => (int) $rows->sum('meal_count'),
                'total' => round($rows->sum('total'), 2),
            ],
            'entries' => array_map(fn (array $entry): array => $entry + [
                'jornal' => $all[$entry['employee_id']]['day_value'],
                'inside' => $all[$entry['employee_id']]['inside'],
            ], $entries),
            'estimated' => $rows->contains('estimated', true),
        ];
    }

    /** Lo que se puede contar en el tablero: todo, o solo lo variable de las horas. */
    public const SCOPES = [
        'todo' => ['jornal', 'novelties', 'surcharges', 'overtime', 'meals'],
        'extras' => ['surcharges', 'overtime'],
    ];

    /**
     * El mismo informe quedándose solo con algunos conceptos: talento humano mira a menudo
     * solo las extras y los recargos, sin el costo fijo del jornal ni la alimentación.
     * Quien no tiene nada en esos conceptos sale de las tablas y de las tortas.
     *
     * @param  array<string, mixed>  $report
     * @param  list<string>  $concepts
     * @return array<string, mixed>
     */
    public static function onlyConcepts(array $report, array $concepts): array
    {
        $excluded = array_diff(array_keys(self::CONCEPTS), $concepts);

        if ($excluded === []) {
            return $report;
        }

        $rows = collect($report['rows'])
            ->map(function (array $row) use ($excluded, $concepts): array {
                foreach ($excluded as $concept) {
                    $row[$concept] = 0.0;
                }

                if (in_array('meals', $excluded, true)) {
                    $row['meal_count'] = 0;
                }

                if (in_array('novelties', $excluded, true)) {
                    $row['novelty_days'] = 0;
                    $row['reimbursed'] = 0.0;
                }

                $row['total'] = round(array_sum(array_map(fn (string $concept): float => $row[$concept], $concepts)), 2);

                return $row;
            })
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        $totals = $report['totals'];

        foreach (['jornal', 'novelties', 'novelty_days', 'reimbursed', 'surcharges', 'surcharge_hours', 'overtime', 'overtime_hours', 'meals', 'meal_count', 'total'] as $key) {
            $totals[$key] = in_array($key, ['meal_count', 'novelty_days'], true) ? (int) $rows->sum($key) : round($rows->sum($key), 2);
        }

        $totals['without_salary'] = $rows->where('without_salary', true)->count();

        return [
            ...$report,
            'rows' => $rows->all(),
            'areas' => self::byArea($rows),
            'totals' => $totals,
            'estimated' => $rows->contains('estimated', true),
        ];
    }

    /** El nombre del área específica de la ficha; sin área, «Sin área». */
    public static function areaLabel(string $area): string
    {
        return EmployeeProfileOptions::SPECIFIC_AREAS[$area] ?? 'Sin área';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRow(Employee $employee): array
    {
        $area = $employee->area_specific && isset(EmployeeProfileOptions::SPECIFIC_AREAS[$employee->area_specific])
            ? $employee->area_specific
            : self::NO_AREA;

        return [
            'employee_id' => $employee->id,
            'code' => $employee->employee_code,
            'name' => $employee->fullName(),
            'position' => $employee->position,
            'area' => $area,
            'area_label' => self::areaLabel($area),
            'present' => 0,
            'present_dates' => [],
            'jornal' => 0.0,
            'novelty_dates' => [],
            'novelties' => 0.0,
            'novelty_days' => 0,
            'reimbursed' => 0.0,
            'inside' => false,
            'without_salary' => (float) $employee->base_salary <= 0,
            'surcharges' => 0.0,
            'surcharge_hours' => 0.0,
            'overtime' => 0.0,
            'overtime_hours' => 0.0,
            'meals' => 0.0,
            'meal_count' => 0,
            'total' => 0.0,
            'estimated' => false,
        ];
    }

    /** Los días del rango en que estuvo contratado: el «de cuántos» de los días que entró. */
    private function employedDays(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $hired = $employee->hire_date?->toDateString();
        $terminated = $employee->termination_date?->toDateString();
        $days = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();

            if (($hired === null || $date >= $hired) && ($terminated === null || $date <= $terminated)) {
                $days++;
            }
        }

        return $days;
    }

    /**
     * Salario ÷ días del mes por cada día del rango en que estuvo contratado. El día de
     * novedad en que no entró no es jornal: ya lo cuentan las novedades pagadas, y el de la
     * no remunerada no se paga.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function chargeJornal(array $row, Employee $employee, string $tenantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $salary = (float) $employee->base_salary;
        $hired = $employee->hire_date?->toDateString();
        $terminated = $employee->termination_date?->toDateString();

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();

            if (($hired !== null && $date < $hired) || ($terminated !== null && $date > $terminated)) {
                continue;
            }

            if (isset($row['novelty_dates'][$date]) && ! isset($row['present_dates'][$date])) {
                continue;
            }

            $row['jornal'] += $salary / $this->parameter(PayrollParameter::MonthDays, CarbonImmutable::parse($date), $tenantId);
        }

        $row['jornal'] = round($row['jornal'], 2);
        $row['day_value'] = round($salary / $this->parameter(PayrollParameter::MonthDays, $to, $tenantId), 2);
        $row['present'] = count($row['present_dates']);
        unset($row['present_dates'], $row['novelty_dates']);

        return $row;
    }

    /**
     * Los días de novedad del rango, con el tipo y el número de día de la incapacidad —el
     * primero, el segundo…—, que es lo que decide quién la paga. Las prórrogas siguen la
     * cuenta: una incapacidad del mismo tipo que empieza al día siguiente de otra es la
     * misma. Por eso se miran también las que empezaron antes del rango.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  Collection<string, Employee>  $employees
     */
    private function addNovelties(array &$rows, Collection $employees, string $tenantId, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $novelties = EmployeeNovelty::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $employees->keys()->all())
            ->overlapping($from->subDays(self::INCAPACITY_LOOKBACK_DAYS), $to)
            ->orderBy('starts_on')
            ->get();

        foreach ($novelties->groupBy('employee_id') as $employeeId => $ofEmployee) {
            $employee = $employees[$employeeId];
            $hired = $employee->hire_date?->toDateString();
            $terminated = $employee->termination_date?->toDateString();
            $chains = [];

            foreach ($ofEmployee as $novelty) {
                $type = $novelty->type;
                $start = CarbonImmutable::parse($novelty->starts_on->toDateString());
                $end = CarbonImmutable::parse($novelty->ends_on->toDateString());
                $chain = $chains[$type->value] ?? null;

                // La prórroga sigue la cuenta de la incapacidad que viene.
                $chainStart = $chain && $start->lessThanOrEqualTo($chain['end']->addDay()) ? $chain['start'] : $start;
                $chains[$type->value] = ['start' => $chainStart, 'end' => $chain ? $end->max($chain['end']) : $end];

                // Las de antes del rango solo cuentan para la cuenta de días.
                if ($end->lessThan($from)) {
                    continue;
                }

                foreach (CarbonPeriod::create($start->max($from), $end->min($to)) as $day) {
                    $date = $day->toDateString();

                    if (($hired !== null && $date < $hired) || ($terminated !== null && $date > $terminated) || isset($rows[$employeeId]['novelty_dates'][$date])) {
                        continue;
                    }

                    $rows[$employeeId]['novelty_dates'][$date] = [
                        'type' => $type,
                        'day' => (int) $chainStart->diffInDays($day) + 1,
                    ];
                }
            }
        }
    }

    /**
     * Cada día de novedad vale lo que paga la nómina: el salario ÷ 30, el mínimo ÷ 30 en la
     * incapacidad al mínimo, o nada si no es remunerada. El día que además entró ya está en
     * el jornal y no se cuenta dos veces.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function chargeNovelties(array $row, Employee $employee, string $tenantId): array
    {
        $salary = (float) $employee->base_salary;

        foreach ($row['novelty_dates'] as $date => $novelty) {
            /** @var NoveltyType $type */
            $type = $novelty['type'];

            if (! $type->isPaid() || isset($row['present_dates'][$date])) {
                continue;
            }

            $day = CarbonImmutable::parse($date);
            $monthDays = $this->parameter(PayrollParameter::MonthDays, $day, $tenantId);
            $minimumDay = $this->parameter(PayrollParameter::Smlmv, $day, $tenantId) / $monthDays;
            $value = $type->dayValueBasis() === NoveltyDayBasis::Smlmv ? $minimumDay : $salary / $monthDays;

            $row['novelties'] += $value;
            $row['novelty_days']++;
            $row['reimbursed'] += $this->reimbursement($type, $novelty['day'], $value, $salary / $monthDays, $minimumDay);
        }

        $row['novelties'] = round($row['novelties'], 2);
        $row['reimbursed'] = round($row['reimbursed'], 2);

        return $row;
    }

    /**
     * Lo que la EPS o la ARL le devuelven a la empresa por un día de incapacidad, según la
     * ley colombiana:
     *
     *  - Accidente de trabajo: la empresa paga el día del accidente; desde el segundo, la
     *    ARL reconoce el 100 % del salario.
     *  - Enfermedad general: los dos primeros días los pone la empresa; del 3 al 90 la EPS
     *    reconoce dos tercios del salario, nunca menos del mínimo; del 91 en adelante, la
     *    mitad. Lo que la empresa paga por encima de eso es suyo.
     *
     *  - Licencia de maternidad o de paternidad: la EPS la reconoce completa desde el
     *    primer día.
     *
     * Permisos, calamidad y vacaciones no tienen reembolso.
     */
    private function reimbursement(NoveltyType $type, int $dayNumber, float $value, float $salaryDay, float $minimumDay): float
    {
        $recognized = match (true) {
            $type === NoveltyType::IncapacidadAt => $dayNumber >= 2 ? $value : 0.0,
            $type === NoveltyType::LicenciaMaternidad, $type === NoveltyType::LicenciaPaternidad => $value,
            $type === NoveltyType::IncapacidadEgSalario, $type === NoveltyType::IncapacidadEgMinimo => match (true) {
                $dayNumber <= 2 => 0.0,
                $dayNumber <= 90 => max($salaryDay * 2 / 3, $minimumDay),
                default => $salaryDay / 2,
            },
            default => 0.0,
        };

        return min($value, $recognized);
    }

    /**
     * Las entradas en portería del rango: cada una enciende el jornal de su día. Devuelve
     * todas, de la más reciente a la más vieja, para el «quién va entrando» del tablero
     * —que se recorre con scroll y tiene buscador—.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return list<array{employee_id: string, name: string, area: string, area_label: string, at: CarbonImmutable}>
     */
    private function addEntries(array &$rows, string $tenantId, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $scans = AttendanceScan::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', array_keys($rows))
            ->where('direction', AttendanceDirection::Entrada)
            ->whereBetween('scanned_at', [
                CarbonImmutable::parse($from->toDateString(), $timezone)->utc(),
                CarbonImmutable::parse($to->toDateString(), $timezone)->endOfDay()->utc(),
            ])
            ->orderByDesc('scanned_at')
            ->get(['employee_id', 'scanned_at']);

        $latest = [];

        foreach ($scans as $scan) {
            $at = CarbonImmutable::instance($scan->scanned_at)->setTimezone($timezone);
            $rows[$scan->employee_id]['present_dates'][$at->toDateString()] = true;

            $latest[] = [
                'employee_id' => $scan->employee_id,
                'name' => $rows[$scan->employee_id]['name'],
                'area' => $rows[$scan->employee_id]['area'],
                'area_label' => $rows[$scan->employee_id]['area_label'],
                'at' => $at,
            ];
        }

        return $latest;
    }

    /**
     * Los recargos y las extras de los días que armó el reloj, confirmados o no.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  Collection<string, Employee>  $employees
     */
    private function addAttendance(array &$rows, Collection $employees, string $tenantId, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $days = AttendanceDay::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $employees->keys()->all())
            ->between($from->toDateString(), $to->toDateString())
            ->get();

        foreach ($days as $day) {
            $employee = $employees[$day->employee_id];
            $row = &$rows[$employee->id];

            if ($day->jornal() > 0) {
                $row['present_dates'][$day->work_date->toDateString()] = true;
            }

            if ($day->status === AttendanceDayStatus::Propuesta) {
                $row['estimated'] = true;
            }

            $this->addHours($row, $day->hours()->paidBuckets(), $employee, $day->work_date, $tenantId);
            unset($row);
        }
    }

    /**
     * Quien entró y no ha salido todavía no tiene día armado: sus horas se clasifican de
     * la entrada hasta ahora, con las mismas reglas del reloj, y quedan como estimado.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @param  Collection<string, Employee>  $employees
     */
    private function addOpenShifts(array &$rows, Collection $employees, string $tenantId, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): void
    {
        $lastScans = AttendanceScan::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $employees->keys()->all())
            ->whereBetween('scanned_at', [$now->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS)->utc(), $now->utc()])
            ->orderBy('scanned_at')
            ->get(['employee_id', 'direction', 'scanned_at'])
            ->keyBy('employee_id')
            ->filter(fn (AttendanceScan $scan): bool => $scan->direction === AttendanceDirection::Entrada);

        // Los días que ya se armaron con horas, de una vez y no uno por uno.
        $built = AttendanceDay::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $lastScans->keys()->all())
            ->between($now->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS)->toDateString(), $now->toDateString())
            ->where('worked_hours', '>', 0)
            ->get(['employee_id', 'work_date'])
            ->mapWithKeys(fn (AttendanceDay $day): array => [$day->employee_id.'|'.$day->work_date->toDateString() => true]);

        foreach ($lastScans as $employeeId => $scan) {
            $entry = CarbonImmutable::instance($scan->scanned_at)->setTimezone($now->getTimezone());
            $workDate = CarbonImmutable::parse($entry->toDateString());

            if ($workDate->lessThan($from) || $workDate->greaterThan($to)) {
                continue;
            }

            $row = &$rows[$employeeId];
            $row['inside'] = true;
            $row['estimated'] = true;

            if (! $built->has($employeeId.'|'.$workDate->toDateString())) {
                $row['present_dates'][$workDate->toDateString()] = true;

                try {
                    $hours = $this->builder->classifyTimes($employees[$employeeId], $workDate, $entry, $now);
                    $this->addHours($row, $hours->paidBuckets(), $employees[$employeeId], $workDate, $tenantId);
                } catch (PayrollParameterException) {
                    // Sin parámetros no hay con qué clasificar: cuenta el jornal y nada más.
                }
            }

            unset($row);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function addMeals(array &$rows, string $tenantId, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): void
    {
        $meals = $this->meals->forEmployees(array_keys($rows), $tenantId, $from, $to, includeUnconfirmed: true, openUntil: $now);

        foreach ($meals as $employeeId => $result) {
            $rows[$employeeId]['meals'] = $result['amount'];
            $rows[$employeeId]['meal_count'] = $result['meals'];
        }
    }

    /**
     * Valora las bolsas como la nómina: salario ÷ divisor × factor. Los recargos pagan solo
     * el recargo, porque la hora ordinaria ya está en el jornal; las extras, la hora entera.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array{hours: float, parameter: PayrollParameter}>  $buckets
     */
    private function addHours(array &$row, array $buckets, Employee $employee, CarbonInterface $day, string $tenantId): void
    {
        $hourValue = (float) $employee->base_salary / $this->parameter(PayrollParameter::MonthlyHoursDivisor, $day, $tenantId);

        foreach ($buckets as $key => $bucket) {
            if ($bucket['hours'] <= 0) {
                continue;
            }

            $amount = $bucket['hours'] * $hourValue * $this->parameter($bucket['parameter'], $day, $tenantId);
            $concept = in_array($key, OvertimeSummary::SURCHARGES, true) ? 'surcharge' : 'overtime';
            $total = $concept === 'surcharge' ? 'surcharges' : 'overtime';

            $row[$total] = round($row[$total] + $amount, 2);
            $row[$concept.'_hours'] = round($row[$concept.'_hours'] + $bucket['hours'], 2);
        }
    }

    /**
     * Los parámetros que valoran se leen con su valor de siembra si la empresa no los
     * tiene: el tablero orienta, no liquida, y no debe caerse por una vigencia que falte.
     */
    private function parameter(PayrollParameter $parameter, CarbonInterface $day, string $tenantId): float
    {
        return $this->parameters->valueOrDefault($parameter, CarbonImmutable::instance($day), $tenantId, $parameter->seedValue()) ?: $parameter->seedValue();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, array{label: string, color: string, people: int, jornal: float, surcharges: float, overtime: float, meals: float, total: float}>
     */
    private static function byArea(Collection $rows): array
    {
        $areas = [];

        foreach (array_keys(self::AREA_COLORS) as $area) {
            $inArea = $rows->where('area', $area);

            if ($inArea->isEmpty()) {
                continue;
            }

            $areas[$area] = [
                'label' => self::areaLabel($area),
                'color' => self::AREA_COLORS[$area],
                'people' => $inArea->count(),
                'jornal' => round($inArea->sum('jornal'), 2),
                'novelties' => round($inArea->sum('novelties'), 2),
                'surcharges' => round($inArea->sum('surcharges'), 2),
                'overtime' => round($inArea->sum('overtime'), 2),
                'meals' => round($inArea->sum('meals'), 2),
                'total' => round($inArea->sum('total'), 2),
            ];
        }

        return $areas;
    }
}
