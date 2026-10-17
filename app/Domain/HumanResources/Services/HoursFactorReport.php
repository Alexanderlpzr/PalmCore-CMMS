<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\EmploymentStatus;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * El indicador «factor de horas» de la extractora (TH-INF-F-002): cuánto se pagó en
 * recargos y horas extras frente a lo ordinario, por grupo, y cómo se movió contra el mes
 * anterior.
 *
 * En el Excel las cifras se teclean una por una; aquí salen de la nómina liquidada. Las
 * horas cuentan igual vayan como horas extras o como bonificación: para el indicador lo
 * que importa es cuánto costaron, no con qué concepto se pagaron.
 *
 * Dos lecturas del informe que se respetan:
 *  - «Recargos» son las horas que solo pagan el recargo (nocturno, dominical): la hora
 *    base ya va en el sueldo. «Extras» pagan la hora completa más su recargo. El informe
 *    las llama «horas con recargos» (las dos juntas) y «horas sin recargos» (solo las
 *    extras); las cuentas del de septiembre solo cuadran leídas así.
 *  - «Apoyo a mantenimiento» son las horas del operario de producción en los días que
 *    talento humano marcó así: salen de su grupo y van a ese renglón.
 *
 * Talento humano lo saca también a mitad del corte («semana 1, 2…»): `toDate()` suma lo
 * que va del corte hasta un día, con las horas del reloj confirmadas o no, y pone como
 * ordinario el sueldo del mes de cada grupo, como el Excel.
 */
class HoursFactorReport
{
    /**
     * Desde el corte de octubre de 2026 la empresa junta MOPE con mantenimiento en el
     * informe («unificación de costos de mantenimiento con MOPE»); antes iba aparte.
     */
    public const MOPE_JOINS_MAINTENANCE_FROM = '2026-09-27';

    public const GROUPS = [
        'produccion' => 'Producción',
        'apoyo_mantenimiento' => 'Apoyo a mantenimiento',
        'mantenimiento' => 'Mantenimiento',
        'mope' => 'Proyecto MOPE',
        'administrativo' => 'Administrativo',
        'sin_area' => 'Sin área',
    ];

    private const SURCHARGES = ['night_surcharge', 'sunday_surcharge', 'night_sunday_surcharge'];

    private const OVERTIME = ['overtime_day', 'overtime_night', 'overtime_sunday_day', 'overtime_sunday_night'];

    public function __construct(
        private readonly LaborCostReport $laborCost,
        private readonly PayrollParameterService $parameters,
    ) {}

    /**
     * Los grupos de la nómina, con sus horas y valores, y el total.
     *
     * @return array{groups: array<string, array{label: string, workers: int, ordinary: float, surcharge_hours: float, surcharge_amount: float, overtime_hours: float, overtime_amount: float, hours: float, amount: float, factor: ?float}>, total: array{label: string, workers: int, ordinary: float, surcharge_hours: float, surcharge_amount: float, overtime_hours: float, overtime_amount: float, hours: float, amount: float, factor: ?float}}
     */
    public function forRun(PayrollRun $run): array
    {
        $rows = $this->emptyRows();
        $periodStart = $run->hoursFrom();
        $entries = $run->entries()->with('employee:id,area_specific')->get();

        foreach ($entries as $entry) {
            $group = self::groupOf($entry->employee?->area_specific, $periodStart);
            $rows[$group]['workers']++;
            $rows[$group]['ordinary'] += (float) $entry->worked_days * (float) $entry->day_value;

            foreach ([...self::SURCHARGES, ...self::OVERTIME] as $bucket) {
                $kind = in_array($bucket, self::SURCHARGES, true) ? 'surcharge' : 'overtime';
                $bonus = $entry->hours_bonus_breakdown[$bucket] ?? ['hours' => 0, 'amount' => 0];

                $rows[$group]["{$kind}_hours"] += (float) $entry->{"{$bucket}_hours"} + (float) $bonus['hours'];
                $rows[$group]["{$kind}_amount"] += (float) $entry->{"{$bucket}_amount"} + (float) $bonus['amount'];
            }
        }

        $this->moveMaintenanceSupport($run, $entries, $rows);

        return $this->assemble($rows);
    }

    /**
     * Lo que va del corte de horas hasta un día —el «semana 1, 2…» de talento humano—:
     * las horas del reloj, confirmadas o no, valoradas como en la nómina, y como ordinario
     * el sueldo del mes de quien está contratado, como en el Excel.
     *
     * @return array{from: CarbonImmutable, until: CarbonImmutable, estimated: bool, groups: array<string, array<string, mixed>>, total: array<string, mixed>}
     */
    public function toDate(Tenant $tenant, CarbonInterface $until, ?CarbonInterface $now = null): array
    {
        $until = CarbonImmutable::parse(CarbonImmutable::instance($until)->toDateString());
        $cutoffDay = (int) $this->parameters->valueOrDefault(PayrollParameter::HoursCutoffDay, $until, $tenant->id);
        [$from] = PayrollRun::hoursWindowContaining($until, $cutoffDay);

        return $this->forRange($tenant, $from, $until, $now);
    }

    /**
     * El mismo tramo del corte anterior —del inicio hasta el mismo número de días—, para
     * comparar lo que va con lo que iba.
     *
     * @return array{from: CarbonImmutable, until: CarbonImmutable, estimated: bool, groups: array<string, array<string, mixed>>, total: array<string, mixed>}
     */
    public function sameStretchBefore(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until, ?CarbonInterface $now = null): array
    {
        $previousFrom = $from->subMonthNoOverflow();

        return $this->forRange($tenant, $previousFrom, $previousFrom->addDays((int) $from->diffInDays($until)), $now);
    }

    /**
     * @return array{from: CarbonImmutable, until: CarbonImmutable, estimated: bool, groups: array<string, array<string, mixed>>, total: array<string, mixed>}
     */
    private function forRange(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $until, ?CarbonInterface $now): array
    {
        $rows = $this->emptyRows();

        $employees = Employee::query()
            ->forTenant($tenant->id)
            ->where(fn ($query) => $query
                ->where('status', EmploymentStatus::Activo)
                ->orWhereDate('termination_date', '>=', $from->toDateString()))
            ->where(fn ($query) => $query->whereNull('hire_date')->orWhereDate('hire_date', '<=', $until->toDateString()))
            ->get(['id', 'area_specific', 'base_salary'])
            ->keyBy('id');

        foreach ($employees as $employee) {
            $group = self::groupOf($employee->area_specific, $from);
            $rows[$group]['workers']++;
            $rows[$group]['ordinary'] += (float) $employee->base_salary;
        }

        $cost = $this->laborCost->forRange($tenant, $from, $until, $now);

        foreach ($cost['rows'] as $row) {
            $group = self::groupOf($employees[$row['employee_id']]->area_specific, $from);
            $rows[$group]['surcharge_hours'] += $row['surcharge_hours'];
            $rows[$group]['surcharge_amount'] += $row['surcharges'];
            $rows[$group]['overtime_hours'] += $row['overtime_hours'];
            $rows[$group]['overtime_amount'] += $row['overtime'];
        }

        $this->moveSupportDays($tenant->id, $employees, $from, $until, $rows);

        return ['from' => $from, 'until' => $until, 'estimated' => $cost['estimated']] + $this->assemble($rows);
    }

    /**
     * Los renglones con sus sumas y el total del informe.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return array{groups: array<string, array<string, mixed>>, total: array<string, mixed>}
     */
    private function assemble(array $rows): array
    {
        $rows = array_map(fn (array $row): array => $this->withTotals($row), $rows);
        $total = $this->emptyRow('Total');

        foreach ($rows as $key => $row) {
            foreach (['ordinary', 'surcharge_hours', 'surcharge_amount', 'overtime_hours', 'overtime_amount'] as $field) {
                $total[$field] += $row[$field];
            }

            // El apoyo son trabajadores que ya están contados en su grupo.
            if ($key !== 'apoyo_mantenimiento') {
                $total['workers'] += $row['workers'];
            }
        }

        return [
            'groups' => array_filter($rows, fn (array $row): bool => $row['workers'] > 0 || $row['amount'] > 0),
            'total' => $this->withTotals($total),
        ];
    }

    /** La nómina anterior de la empresa, con la que se compara. */
    public function previousRun(PayrollRun $run): ?PayrollRun
    {
        return PayrollRun::query()
            ->forTenant($run->tenant_id)
            ->whereDate('period_start', '<', $run->period_start)
            ->whereNotNull('calculated_at')
            ->orderByDesc('period_start')
            ->first();
    }

    /**
     * El grupo del informe al que pertenece un área de la ficha. Con el inicio del corte,
     * MOPE va con mantenimiento desde que la empresa los juntó.
     */
    public static function groupOf(?string $areaSpecific, ?CarbonInterface $periodStart = null): string
    {
        if ($areaSpecific === 'mope' && $periodStart && $periodStart->toDateString() >= self::MOPE_JOINS_MAINTENANCE_FROM) {
            return 'mantenimiento';
        }

        return match ($areaSpecific) {
            'procesos', 'laboratorio', 'calidad', 'oficios_varios' => 'produccion',
            'mantenimiento' => 'mantenimiento',
            'mope' => 'mope',
            'administrativo' => 'administrativo',
            default => 'sin_area',
        };
    }

    /**
     * Las horas de los días marcados como apoyo a mantenimiento, valoradas con la tarifa
     * con que se liquidaron, pasan del grupo del trabajador al renglón de apoyo. Quien ya
     * es de mantenimiento no se mueve.
     *
     * @param  Collection<int, PayrollEntry>  $entries
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function moveMaintenanceSupport(PayrollRun $run, Collection $entries, array &$rows): void
    {
        $days = AttendanceDay::query()
            ->forTenant($run->tenant_id)
            ->confirmed()
            ->between($run->hoursFrom()->toDateString(), $run->hoursTo()->toDateString())
            ->where('maintenance_support', true)
            ->get()
            ->groupBy('employee_id');

        foreach ($entries as $entry) {
            $group = self::groupOf($entry->employee?->area_specific, $run->hoursFrom());

            if ($group === 'mantenimiento' || ! $days->has($entry->employee_id)) {
                continue;
            }

            $rows['apoyo_mantenimiento']['workers']++;
            $factors = $entry->parameters_snapshot ?? [];

            foreach ($days[$entry->employee_id] as $day) {
                foreach ($day->hours()->paidBuckets() as $bucket => $paid) {
                    if ($paid['hours'] <= 0) {
                        continue;
                    }

                    $kind = in_array($bucket, self::SURCHARGES, true) ? 'surcharge' : 'overtime';
                    $amount = $paid['hours'] * (float) $entry->hour_value * (float) ($factors[$paid['parameter']->value] ?? 0);

                    $rows[$group]["{$kind}_hours"] -= $paid['hours'];
                    $rows[$group]["{$kind}_amount"] -= $amount;
                    $rows['apoyo_mantenimiento']["{$kind}_hours"] += $paid['hours'];
                    $rows['apoyo_mantenimiento']["{$kind}_amount"] += $amount;
                }
            }
        }
    }

    /**
     * Lo mismo a mitad del corte: los días marcados como apoyo, confirmados o no, valorados
     * con el sueldo de la ficha (salario ÷ divisor × factor), como el resto del acumulado.
     *
     * @param  Collection<string, Employee>  $employees
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function moveSupportDays(string $tenantId, Collection $employees, CarbonImmutable $from, CarbonImmutable $until, array &$rows): void
    {
        $days = AttendanceDay::query()
            ->forTenant($tenantId)
            ->whereIn('employee_id', $employees->keys()->all())
            ->between($from->toDateString(), $until->toDateString())
            ->where('maintenance_support', true)
            ->get();

        $supported = [];

        foreach ($days as $day) {
            $employee = $employees[$day->employee_id];
            $group = self::groupOf($employee->area_specific, $from);

            if ($group === 'mantenimiento') {
                continue;
            }

            $supported[$employee->id] = true;
            $hourValue = (float) $employee->base_salary / ($this->parameter(PayrollParameter::MonthlyHoursDivisor, $day->work_date, $tenantId) ?: 1);

            foreach ($day->hours()->paidBuckets() as $bucket => $paid) {
                if ($paid['hours'] <= 0) {
                    continue;
                }

                $kind = in_array($bucket, self::SURCHARGES, true) ? 'surcharge' : 'overtime';
                $amount = $paid['hours'] * $hourValue * $this->parameter($paid['parameter'], $day->work_date, $tenantId);

                $rows[$group]["{$kind}_hours"] -= $paid['hours'];
                $rows[$group]["{$kind}_amount"] -= $amount;
                $rows['apoyo_mantenimiento']["{$kind}_hours"] += $paid['hours'];
                $rows['apoyo_mantenimiento']["{$kind}_amount"] += $amount;
            }
        }

        $rows['apoyo_mantenimiento']['workers'] += count($supported);
    }

    /** Como el tablero de costo de personal: con el valor de siembra si falta la vigencia. */
    private function parameter(PayrollParameter $parameter, CarbonInterface $day, string $tenantId): float
    {
        return $this->parameters->valueOrDefault($parameter, CarbonImmutable::instance($day), $tenantId, $parameter->seedValue()) ?: $parameter->seedValue();
    }

    /** @return array<string, array{label: string, workers: int, ordinary: float, surcharge_hours: float, surcharge_amount: float, overtime_hours: float, overtime_amount: float}> */
    private function emptyRows(): array
    {
        return collect(self::GROUPS)->map(fn (string $label): array => $this->emptyRow($label))->all();
    }

    /** @return array{label: string, workers: int, ordinary: float, surcharge_hours: float, surcharge_amount: float, overtime_hours: float, overtime_amount: float} */
    private function emptyRow(string $label): array
    {
        return [
            'label' => $label,
            'workers' => 0,
            'ordinary' => 0.0,
            'surcharge_hours' => 0.0,
            'surcharge_amount' => 0.0,
            'overtime_hours' => 0.0,
            'overtime_amount' => 0.0,
        ];
    }

    /**
     * Las sumas del renglón y el factor: cuánto se pagó en recargos y extras por cada peso
     * ordinario del grupo. El apoyo a mantenimiento no tiene ordinario propio —su sueldo
     * sigue en producción—, así que no tiene factor.
     */
    private function withTotals(array $row): array
    {
        $row['hours'] = round($row['surcharge_hours'] + $row['overtime_hours'], 2);
        $row['amount'] = round($row['surcharge_amount'] + $row['overtime_amount'], 2);
        $row['factor'] = $row['ordinary'] > 0 ? round($row['amount'] / $row['ordinary'], 4) : null;

        return $row;
    }
}
