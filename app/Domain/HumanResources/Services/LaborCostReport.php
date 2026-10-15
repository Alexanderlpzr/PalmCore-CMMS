<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Exceptions\PayrollParameterException;
use App\Domain\HumanResources\Support\EmployeeProfileOptions;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
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
 *  - **Jornal**: salario ÷ días del mes (30) por cada día en que la persona entró a la
 *    extractora —una entrada en portería o un día con horas—. Arranca en cero y crece con
 *    cada carné que se escanea (cambio pedido por la empresa el mismo 2026-10-15: antes
 *    sumaba a todo activo, marcara o no).
 *  - **Recargos y horas extras**: las horas del reloj valoradas como en la nómina,
 *    salario ÷ divisor × factor. Cuentan también las propuestas sin confirmar y el turno
 *    que sigue abierto (hasta ahora), y por eso esas cifras van marcadas «estimado».
 *    Las que la regla del bono pase a bonificación cuestan lo mismo y entran igual.
 *  - **Alimentación**: el auxilio por franja cubierta, con la misma estimación.
 *
 * No incluye prestaciones, aportes patronales ni parafiscales: es el costo directo del día.
 */
class LaborCostReport
{
    /** @var array<string, string> */
    public const CONCEPTS = [
        'jornal' => 'Jornales',
        'surcharges' => 'Recargos',
        'overtime' => 'Horas extras',
        'meals' => 'Alimentación',
    ];

    /** @var array<string, string> */
    public const CONCEPT_COLORS = [
        'jornal' => FrondaPalette::Petrol[600],
        'surcharges' => FrondaPalette::Petrol[300],
        'overtime' => FrondaPalette::Brand[600],
        'meals' => '#d97706',
    ];

    public const NO_AREA = 'sin_area';

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
     *     rows: list<array{employee_id: string, code: ?string, name: string, position: ?string, area: string, area_label: string, days: int, present: int, inside: bool, without_salary: bool, jornal: float, surcharges: float, surcharge_hours: float, overtime: float, overtime_hours: float, meals: float, meal_count: int, total: float, estimated: bool}>,
     *     areas: array<string, array{label: string, color: string, people: int, jornal: float, surcharges: float, overtime: float, meals: float, total: float}>,
     *     totals: array{people: int, present: int, inside: int, without_salary: int, jornal: float, surcharges: float, surcharge_hours: float, overtime: float, overtime_hours: float, meals: float, meal_count: int, total: float},
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

        $all = collect($rows)->map(function (array $row) use ($employees, $tenant): array {
            $row = $this->chargeJornal($row, $employees[$row['employee_id']], $tenant->id);
            $row['total'] = round($row['jornal'] + $row['surcharges'] + $row['overtime'] + $row['meals'], 2);

            return $row;
        });

        // En las tablas y las tortas, solo quien ya costó algo: quien no ha entrado vale cero.
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
                'surcharges' => round($rows->sum('surcharges'), 2),
                'surcharge_hours' => round($rows->sum('surcharge_hours'), 2),
                'overtime' => round($rows->sum('overtime'), 2),
                'overtime_hours' => round($rows->sum('overtime_hours'), 2),
                'meals' => round($rows->sum('meals'), 2),
                'meal_count' => (int) $rows->sum('meal_count'),
                'total' => round($rows->sum('total'), 2),
            ],
            'entries' => array_map(fn (array $entry): array => $entry + [
                'jornal' => $all[$entry['employee_id']]['jornal'] / max(1, $all[$entry['employee_id']]['present']),
                'inside' => $all[$entry['employee_id']]['inside'],
            ], $entries),
            'estimated' => $rows->contains('estimated', true),
        ];
    }

    /** Lo que se puede contar en el tablero: todo, o solo lo variable de las horas. */
    public const SCOPES = [
        'todo' => ['jornal', 'surcharges', 'overtime', 'meals'],
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

                $row['total'] = round(array_sum(array_map(fn (string $concept): float => $row[$concept], $concepts)), 2);

                return $row;
            })
            ->filter(fn (array $row): bool => $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        $totals = $report['totals'];

        foreach (['jornal', 'surcharges', 'surcharge_hours', 'overtime', 'overtime_hours', 'meals', 'meal_count', 'total'] as $key) {
            $totals[$key] = $key === 'meal_count' ? (int) $rows->sum($key) : round($rows->sum($key), 2);
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
     * Salario ÷ días del mes por cada día en que entró. El día cuenta una vez, aunque haya
     * entrado y salido varias veces.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function chargeJornal(array $row, Employee $employee, string $tenantId): array
    {
        $salary = (float) $employee->base_salary;

        foreach (array_keys($row['present_dates']) as $date) {
            $row['jornal'] += $salary / $this->parameter(PayrollParameter::MonthDays, CarbonImmutable::parse($date), $tenantId);
        }

        $row['jornal'] = round($row['jornal'], 2);
        $row['present'] = count($row['present_dates']);
        unset($row['present_dates']);

        return $row;
    }

    /**
     * Las entradas en portería del rango: cada una enciende el jornal de su día. Devuelve
     * las últimas, para el «quién va entrando» del tablero.
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

            if (count($latest) < 8) {
                $latest[] = [
                    'employee_id' => $scan->employee_id,
                    'name' => $rows[$scan->employee_id]['name'],
                    'area' => $rows[$scan->employee_id]['area'],
                    'area_label' => $rows[$scan->employee_id]['area_label'],
                    'at' => $at,
                ];
            }
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

        foreach ($lastScans as $employeeId => $scan) {
            $entry = CarbonImmutable::instance($scan->scanned_at)->setTimezone($now->getTimezone());
            $workDate = CarbonImmutable::parse($entry->toDateString());

            if ($workDate->lessThan($from) || $workDate->greaterThan($to)) {
                continue;
            }

            $row = &$rows[$employeeId];
            $row['inside'] = true;
            $row['estimated'] = true;

            $alreadyBuilt = AttendanceDay::query()
                ->forTenant($tenantId)
                ->where('employee_id', $employeeId)
                ->whereDate('work_date', $workDate->toDateString())
                ->where('worked_hours', '>', 0)
                ->exists();

            if (! $alreadyBuilt) {
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
                'surcharges' => round($inArea->sum('surcharges'), 2),
                'overtime' => round($inArea->sum('overtime'), 2),
                'meals' => round($inArea->sum('meals'), 2),
                'total' => round($inArea->sum('total'), 2),
            ];
        }

        return $areas;
    }
}
