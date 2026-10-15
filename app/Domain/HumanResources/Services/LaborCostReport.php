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
 *  - **Jornal**: cada trabajador activo suma salario ÷ días del mes (30) por cada día del
 *    rango en que estuvo contratado, marque o no en portería —el sueldo corre igual—.
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
            $rows[$employee->id] = $this->emptyRow($employee) + $this->jornal($employee, $from, $to, $tenant->id);
        }

        $this->addAttendance($rows, $employees, $tenant->id, $from, $to);
        $this->addOpenShifts($rows, $employees, $tenant->id, $from, $to, $now);
        $this->addMeals($rows, $tenant->id, $from, $to, $now);

        $rows = collect($rows)
            ->map(function (array $row): array {
                $row['total'] = round($row['jornal'] + $row['surcharges'] + $row['overtime'] + $row['meals'], 2);

                return $row;
            })
            ->filter(fn (array $row): bool => $row['days'] > 0 || $row['total'] > 0)
            ->sortByDesc('total')
            ->values();

        return [
            'rows' => $rows->all(),
            'areas' => $this->byArea($rows),
            'totals' => [
                'people' => $rows->count(),
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

    /**
     * Salario ÷ días del mes por cada día del rango en que estuvo contratado.
     *
     * @return array{days: int, jornal: float}
     */
    private function jornal(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, string $tenantId): array
    {
        $salary = (float) $employee->base_salary;
        $hired = $employee->hire_date?->toDateString();
        $terminated = $employee->termination_date?->toDateString();
        $days = 0;
        $amount = 0.0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();

            if (($hired !== null && $date < $hired) || ($terminated !== null && $date > $terminated)) {
                continue;
            }

            $days++;
            $amount += $salary / $this->parameter(PayrollParameter::MonthDays, $day, $tenantId);
        }

        return ['days' => $days, 'jornal' => round($amount, 2)];
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
                $row['present']++;
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
                $row['present']++;

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
    private function byArea(Collection $rows): array
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
