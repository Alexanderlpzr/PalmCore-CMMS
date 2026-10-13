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
use Illuminate\Support\Collection;

/**
 * Las cifras del tablero de Talento Humano: lo que requiere atención hoy, cómo va el corte
 * de horas en curso y cómo se movieron las horas extras en los últimos cortes.
 *
 * Todo sale de los mismos datos que las pantallas de trabajo —marcas, días, novedades—
 * y nada se guarda: el tablero es una mirada, no una fuente. Cada número lleva a la
 * pantalla donde se resuelve.
 */
class HumanResourcesDashboard
{
    /** Hacia atrás que se buscan las entradas que nadie cerró. */
    public const OPEN_ENTRIES_LOOKBACK_DAYS = 30;

    private const SURCHARGE_COLUMNS = ['night_surcharge_hours', 'sunday_surcharge_hours', 'night_sunday_surcharge_hours'];

    private const OVERTIME_COLUMNS = ['overtime_day_hours', 'overtime_night_hours', 'overtime_sunday_day_hours', 'overtime_sunday_night_hours'];

    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly PayrollParameterService $parameters,
    ) {}

    /**
     * Lo que requiere atención hoy.
     *
     * @return array{inside: int, markedToday: int, openEntries: int, toConfirm: int, contractsEnding: int, withoutSalary: int, withoutBadge: int}
     */
    public function attention(Tenant $tenant): array
    {
        $timezone = $tenant->plantTimezone();
        $today = CarbonImmutable::now($timezone);
        [$from] = $this->currentWindow($tenant);

        $active = Employee::query()->forTenant($tenant->id)->active();

        return [
            'inside' => $this->peopleInside($tenant),
            'markedToday' => AttendanceScan::query()
                ->forTenant($tenant->id)
                ->whereBetween('scanned_at', [$today->startOfDay()->utc(), $today->endOfDay()->utc()])
                ->distinct()
                ->count('employee_id'),
            'openEntries' => $this->openEntries($tenant)->count(),
            'toConfirm' => AttendanceDay::query()
                ->forTenant($tenant->id)
                ->proposed()
                ->between($from->toDateString(), $today->toDateString())
                ->count(),
            'contractsEnding' => (clone $active)
                ->where('contract_type', 'fijo')
                ->whereBetween('contract_end_date', [$today->toDateString(), $today->addDays(30)->toDateString()])
                ->count(),
            'withoutSalary' => (clone $active)
                ->where(fn ($query) => $query->whereNull('base_salary')->orWhere('base_salary', '<=', 0))
                ->count(),
            'withoutBadge' => (clone $active)->whereDoesntHave('qrCode')->count(),
        ];
    }

    /**
     * El corte de horas en curso: cuánto falta, qué tanto está confirmado y cuántas horas
     * extras y recargos lleva, contra el corte anterior a la misma altura.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, totalDays: int, elapsedDays: int, daysLeft: int, confirmed: int, proposed: int, confirmedPercent: int, overtime: float, surcharges: float, previousOvertime: float, previousSurcharges: float}
     */
    public function currentCutoff(Tenant $tenant): array
    {
        $today = CarbonImmutable::now($tenant->plantTimezone())->startOfDay();
        [$from, $to] = $this->currentWindow($tenant);

        $days = AttendanceDay::query()
            ->forTenant($tenant->id)
            ->between($from->toDateString(), $to->toDateString())
            ->get();

        $confirmed = $days->where('status', AttendanceDayStatus::Confirmada)->count();
        $proposed = $days->where('status', AttendanceDayStatus::Propuesta)->count();
        $elapsed = (int) $from->diffInDays($today) + 1;

        // El corte anterior hasta el mismo día del corte: comparar el mes entero contra
        // diez días de este haría parecer que todo bajó.
        [$previousFrom] = PayrollRun::hoursWindowContaining($from->subDay(), $this->cutoffDay($tenant));
        $previous = AttendanceDay::query()
            ->forTenant($tenant->id)
            ->between($previousFrom->toDateString(), $previousFrom->addDays($elapsed - 1)->toDateString())
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'totalDays' => (int) $from->diffInDays($to) + 1,
            'elapsedDays' => $elapsed,
            'daysLeft' => max(0, (int) $today->diffInDays($to)),
            'confirmed' => $confirmed,
            'proposed' => $proposed,
            'confirmedPercent' => $confirmed + $proposed > 0 ? (int) round($confirmed * 100 / ($confirmed + $proposed)) : 100,
            'overtime' => round($this->sum($days, self::OVERTIME_COLUMNS), 2),
            'surcharges' => round($this->sum($days, self::SURCHARGE_COLUMNS), 2),
            'previousOvertime' => round($this->sum($previous, self::OVERTIME_COLUMNS), 2),
            'previousSurcharges' => round($this->sum($previous, self::SURCHARGE_COLUMNS), 2),
        ];
    }

    /**
     * Horas extras por grupo en los últimos cortes, el más viejo primero. El día marcado
     * como apoyo a mantenimiento va a ese grupo, como en el indicador «factor de horas».
     *
     * @return array{labels: list<string>, groups: array<string, list<float>>}
     */
    public function overtimeByGroup(Tenant $tenant, int $windows = 6): array
    {
        $ranges = array_reverse(array_keys(PayrollRun::recentHoursWindows($this->cutoffDay($tenant), $windows, CarbonImmutable::now($tenant->plantTimezone()))));
        $labels = [];
        $groups = [];

        $first = explode('|', $ranges[0])[0];
        $last = explode('|', end($ranges))[1];

        $days = AttendanceDay::query()
            ->forTenant($tenant->id)
            ->between($first, $last)
            ->with('employee:id,area_specific')
            ->get();

        foreach ($ranges as $index => $range) {
            [$from, $to] = explode('|', $range);
            $labels[] = 'Al '.CarbonImmutable::parse($to)->format('d/m');

            foreach ($days as $day) {
                $date = $day->work_date->toDateString();

                if ($date < $from || $date > $to) {
                    continue;
                }

                $group = $day->maintenance_support ? 'apoyo_mantenimiento' : HoursFactorReport::groupOf($day->employee?->area_specific);
                $groups[$group] ??= array_fill(0, count($ranges), 0.0);
                $groups[$group][$index] += $this->sum(collect([$day]), self::OVERTIME_COLUMNS);
            }
        }

        $ordered = [];

        foreach (HoursFactorReport::GROUPS as $key => $label) {
            if (isset($groups[$key]) && array_sum($groups[$key]) > 0) {
                $ordered[$label] = array_map(fn (float $hours): float => round($hours, 1), $groups[$key]);
            }
        }

        return ['labels' => $labels, 'groups' => $ordered];
    }

    /**
     * Días de novedad del corte en curso por tipo: incapacidades, vacaciones, permisos.
     *
     * @return array<string, int>
     */
    public function noveltiesByType(Tenant $tenant): array
    {
        [$from, $to] = $this->currentWindow($tenant);

        return EmployeeNovelty::query()
            ->forTenant($tenant->id)
            ->overlapping($from, $to)
            ->get()
            ->groupBy(fn (EmployeeNovelty $novelty): string => $novelty->type->label())
            ->map(fn ($novelties): int => $novelties->sum(fn (EmployeeNovelty $novelty): int => $novelty->daysWithin($from, $to)))
            ->filter()
            ->sortDesc()
            ->all();
    }

    /**
     * La ventana de horas que corre hoy: con corte 26, del 27 del mes pasado al 26.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function currentWindow(Tenant $tenant): array
    {
        return PayrollRun::hoursWindowContaining(CarbonImmutable::now($tenant->plantTimezone()), $this->cutoffDay($tenant));
    }

    /**
     * Las entradas de los últimos días que nadie cerró, sin contar a quien sigue adentro.
     *
     * @return Collection<int, AttendanceScan>
     */
    public function openEntries(Tenant $tenant)
    {
        $now = CarbonImmutable::now();
        $limit = $now->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS);

        return $this->attendance
            ->openEntries($tenant->id, $now->subDays(self::OPEN_ENTRIES_LOOKBACK_DAYS)->utc(), $now->utc())
            ->filter(fn (AttendanceScan $scan): bool => $scan->scanned_at->lte($limit))
            ->values();
    }

    /** Su última marca de las últimas 16 horas es una entrada: como cuenta la Portería. */
    private function peopleInside(Tenant $tenant): int
    {
        return AttendanceScan::query()
            ->forTenant($tenant->id)
            ->where('scanned_at', '>=', now()->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS))
            ->orderBy('scanned_at')
            ->get(['employee_id', 'direction'])
            ->keyBy('employee_id')
            ->filter(fn (AttendanceScan $scan): bool => $scan->direction === AttendanceDirection::Entrada)
            ->count();
    }

    private function cutoffDay(Tenant $tenant): int
    {
        return (int) $this->parameters->valueOrDefault(PayrollParameter::HoursCutoffDay, CarbonImmutable::today(), $tenant->id);
    }

    /**
     * @param  Collection<int, AttendanceDay>  $days
     * @param  list<string>  $columns
     */
    private function sum($days, array $columns): float
    {
        return (float) $days->sum(fn (AttendanceDay $day): float => array_sum(array_map(fn (string $column): float => (float) $day->{$column}, $columns)));
    }
}
