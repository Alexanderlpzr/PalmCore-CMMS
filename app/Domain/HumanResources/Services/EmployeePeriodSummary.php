<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Todo lo de una persona en un corte, en un solo lugar: lo que abre la ventana «Detalle»
 * desde las tablas de horas.
 *
 * Junta lo que antes estaba repartido en cuatro pantallas —sus días con entrada y salida y
 * las horas de cada uno, las comidas, lo que costó y sus novedades— y lo compara con su
 * corte anterior a la misma altura, para que se vea si viene haciendo más horas que antes.
 *
 * Las horas incluyen los días sin confirmar, marcados así; las comidas, solo las de días
 * confirmados, que son las que se pagan.
 */
class EmployeePeriodSummary
{
    public const SURCHARGE_COLUMNS = ['night_surcharge_hours', 'sunday_surcharge_hours', 'night_sunday_surcharge_hours'];

    public const OVERTIME_COLUMNS = ['overtime_day_hours', 'overtime_night_hours', 'overtime_sunday_day_hours', 'overtime_sunday_night_hours'];

    public function __construct(
        private readonly PayrollParameterService $parameters,
        private readonly MealAllowanceCalculator $meals,
        private readonly LaborCostReport $costs,
    ) {}

    /**
     * @return array{
     *     employee: Employee,
     *     area: string,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     until: CarbonImmutable,
     *     days: list<array{date: CarbonImmutable, entry: ?CarbonImmutable, exit: ?CarbonImmutable, worked: float, ordinary: float, surcharges: float, overtime: float, status: ?AttendanceDayStatus, adjusted: bool, anomalies: list<string>, meals: list<string>, novelty: ?string, sunday: bool}>,
     *     totals: array{worked_days: int, confirmed: int, proposed: int, worked: float, ordinary: float, surcharges: float, overtime: float, meal_count: int, meal_amount: float, novelty_days: int},
     *     cost: ?array<string, mixed>,
     *     novelties: list<array{type: string, from: CarbonImmutable, to: CarbonImmutable, days: int, notes: ?string}>,
     *     previous: ?array{from: CarbonImmutable, to: CarbonImmutable, worked_days: int, worked: float, surcharges: float, overtime: float},
     * }
     */
    public function for(Employee $employee, CarbonInterface $from, CarbonInterface $to, bool $withMoney = false): array
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($employee->tenant_id);
        $timezone = $tenant->plantTimezone();
        $from = CarbonImmutable::parse(CarbonImmutable::instance($from)->toDateString());
        $to = CarbonImmutable::parse(CarbonImmutable::instance($to)->toDateString());
        $today = CarbonImmutable::parse(CarbonImmutable::now($timezone)->toDateString());
        // Lo que no ha pasado no se muestra: un corte en curso llega hasta hoy.
        $until = $to->greaterThan($today) ? $today : $to;

        $attendance = AttendanceDay::query()
            ->forTenant($tenant->id)
            ->where('employee_id', $employee->id)
            ->between($from->toDateString(), $until->toDateString())
            ->get()
            ->keyBy(fn (AttendanceDay $day): string => $day->work_date->toDateString());

        $shifts = $this->shifts($employee, $from, $until, $timezone);
        $meals = $this->meals->forEmployees([$employee->id], $tenant->id, $from, $until)[$employee->id] ?? null;

        $novelties = EmployeeNovelty::query()
            ->forTenant($tenant->id)
            ->where('employee_id', $employee->id)
            ->overlapping($from, $until)
            ->orderBy('starts_on')
            ->get();

        $days = [];

        foreach (CarbonPeriod::create($from, $until) as $date) {
            $date = CarbonImmutable::instance($date);
            $key = $date->toDateString();
            /** @var AttendanceDay|null $day */
            $day = $attendance[$key] ?? null;
            [$entry, $exit] = $shifts[$key] ?? [null, null];

            if ($day?->isManuallyAdjusted() && $day->adjusted_entry_at && $day->adjusted_exit_at) {
                $entry = CarbonImmutable::instance($day->adjusted_entry_at)->setTimezone($timezone);
                $exit = CarbonImmutable::instance($day->adjusted_exit_at)->setTimezone($timezone);
            }

            $days[] = [
                'date' => $date,
                'entry' => $entry,
                'exit' => $exit,
                'worked' => round((float) ($day?->worked_hours ?? 0), 2),
                'ordinary' => round((float) ($day?->ordinary_hours ?? 0), 2),
                'surcharges' => round($day ? $this->sum($day, self::SURCHARGE_COLUMNS) : 0.0, 2),
                'overtime' => round($day ? $this->sum($day, self::OVERTIME_COLUMNS) : 0.0, 2),
                'status' => $day?->status,
                'adjusted' => (bool) $day?->isManuallyAdjusted(),
                'anomalies' => array_values($day?->anomalies ?? []),
                // En singular: en un día hay un desayuno, no «desayunos».
                'meals' => array_map(fn (string $meal): string => ucfirst(str_replace('_', ' ', $meal)), $meals['days'][$key] ?? []),
                'novelty' => $novelties->first(fn (EmployeeNovelty $n): bool => $n->starts_on->toDateString() <= $key && $n->ends_on->toDateString() >= $key)?->type->label(),
                'sunday' => $date->isSunday(),
            ];
        }

        $worked = collect($days)->filter(fn (array $d): bool => $d['worked'] > 0);

        return [
            'employee' => $employee,
            'area' => LaborCostReport::areaLabel($employee->area_specific ?: LaborCostReport::NO_AREA),
            'from' => $from,
            'to' => $to,
            'until' => $until,
            'days' => $days,
            'totals' => [
                'worked_days' => $worked->count(),
                'confirmed' => $attendance->where('status', AttendanceDayStatus::Confirmada)->count(),
                'proposed' => $attendance->where('status', AttendanceDayStatus::Propuesta)->count(),
                'worked' => round($worked->sum('worked'), 2),
                'ordinary' => round($worked->sum('ordinary'), 2),
                'surcharges' => round(collect($days)->sum('surcharges'), 2),
                'overtime' => round(collect($days)->sum('overtime'), 2),
                'meal_count' => (int) ($meals['meals'] ?? 0),
                'meal_amount' => (float) ($meals['amount'] ?? 0),
                'novelty_days' => $novelties->sum(fn (EmployeeNovelty $n): int => $n->daysWithin($from, $until)),
            ],
            'cost' => $withMoney ? $this->cost($tenant, $employee, $from, $until) : null,
            'novelties' => $novelties->map(fn (EmployeeNovelty $n): array => [
                'type' => $n->type->label(),
                'from' => CarbonImmutable::instance($n->starts_on),
                'to' => CarbonImmutable::instance($n->ends_on),
                'days' => $n->daysWithin($from, $until),
                'notes' => $n->notes,
            ])->values()->all(),
            'previous' => $this->previous($tenant, $employee, $from, $until),
        ];
    }

    /**
     * La primera entrada y la última salida de cada día, en la hora de la planta. El turno
     * es del día en que entró, aunque salga al siguiente.
     *
     * @return array<string, array{0: CarbonImmutable, 1: ?CarbonImmutable}>
     */
    private function shifts(Employee $employee, CarbonImmutable $from, CarbonImmutable $until, string $timezone): array
    {
        $scans = AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [
                CarbonImmutable::parse($from->toDateString(), $timezone)->utc(),
                CarbonImmutable::parse($until->toDateString(), $timezone)->addDays(2)->utc(),
            ])
            ->orderBy('scanned_at')
            ->get(['direction', 'scanned_at']);

        $shifts = [];
        $openDate = null;

        foreach ($scans as $scan) {
            $at = CarbonImmutable::instance($scan->scanned_at)->setTimezone($timezone);

            if ($scan->direction === AttendanceDirection::Entrada) {
                $openDate = $at->toDateString();

                if ($openDate <= $until->toDateString()) {
                    $shifts[$openDate] ??= [$at, null];
                }

                continue;
            }

            if ($openDate !== null && isset($shifts[$openDate])) {
                $shifts[$openDate][1] = $at;
            }
        }

        return $shifts;
    }

    /**
     * Lo que costó en el corte: jornal, recargos, extras y alimentación, como en «Costo de
     * personal».
     *
     * @return array<string, mixed>|null
     */
    private function cost(Tenant $tenant, Employee $employee, CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        $report = $this->costs->forRange($tenant, $from, $until);

        return collect($report['rows'])->firstWhere('employee_id', $employee->id);
    }

    /**
     * Su corte anterior hasta la misma altura: comparar el mes entero contra diez días de
     * este haría parecer que todo bajó.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, worked_days: int, worked: float, surcharges: float, overtime: float}|null
     */
    private function previous(Tenant $tenant, Employee $employee, CarbonImmutable $from, CarbonImmutable $until): ?array
    {
        $cutoffDay = (int) $this->parameters->valueOrDefault(PayrollParameter::HoursCutoffDay, $from, $tenant->id);
        [$previousFrom] = PayrollRun::hoursWindowContaining($from->subDay(), $cutoffDay);
        $previousTo = $previousFrom->addDays((int) $from->diffInDays($until));

        if ($previousTo->greaterThanOrEqualTo($from)) {
            return null;
        }

        /** @var Collection<int, AttendanceDay> $days */
        $days = AttendanceDay::query()
            ->forTenant($tenant->id)
            ->where('employee_id', $employee->id)
            ->between($previousFrom->toDateString(), $previousTo->toDateString())
            ->get();

        if ($days->isEmpty()) {
            return null;
        }

        return [
            'from' => $previousFrom,
            'to' => $previousTo,
            'worked_days' => $days->filter(fn (AttendanceDay $d): bool => $d->jornal() > 0)->count(),
            'worked' => round((float) $days->sum('worked_hours'), 2),
            'surcharges' => round($days->sum(fn (AttendanceDay $d): float => $this->sum($d, self::SURCHARGE_COLUMNS)), 2),
            'overtime' => round($days->sum(fn (AttendanceDay $d): float => $this->sum($d, self::OVERTIME_COLUMNS)), 2),
        ];
    }

    /** @param  list<string>  $columns */
    private function sum(AttendanceDay $day, array $columns): float
    {
        return array_sum(array_map(fn (string $column): float => (float) $day->{$column}, $columns));
    }
}
