<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeNovelty;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * La jornada presunta de los administrativos: el que trabaja en la oficina y no marca en
 * portería cumplió su jornada legal, de 6 a. m. a 2 p. m. con una hora de almuerzo.
 *
 * Cada día laboral sin ninguna marca —lunes a sábado, sin festivos ni novedades— se llena
 * con esas cuatro marcas a mano, y el día se arma como cualquier otro: siete horas
 * ordinarias y las comidas de su franja. Así queda a la vista en Revisar horas, se puede
 * anular y talento humano lo confirma como siempre.
 *
 * El día con una marca, aunque esté anulada, no se presume: mandan las marcas de la puerta,
 * y una presunción que talento humano anuló no vuelve a aparecer la noche siguiente.
 *
 * Rige mientras el parámetro {@see PayrollParameter::PresumeAdministrativeWorkday} esté
 * prendido en ese día, y nunca hoy ni hacia adelante: el día se presume ya terminado.
 */
class PresumedWorkdayService
{
    /** El área de la ficha a la que se le presume la jornada. */
    public const AREA = 'administrativo';

    /** El motivo que llevan las marcas presuntas, con el que se reconocen. */
    public const NOTE = 'Jornada presunta: administrativo sin marcas en portería';

    /** @var list<array{0: string, 1: AttendanceDirection}> */
    public const MARKS = [
        ['06:00', AttendanceDirection::Entrada],
        ['12:00', AttendanceDirection::Salida],
        ['13:00', AttendanceDirection::Entrada],
        ['14:00', AttendanceDirection::Salida],
    ];

    public function __construct(
        private readonly AttendanceDayBuilder $builder,
        private readonly PayrollParameterService $parameters,
    ) {}

    /**
     * Llena los días sin marcas del rango, hasta ayer, y los arma.
     *
     * @return Collection<int, array{employee: Employee, dates: list<string>}> a quién se le presumió qué días
     */
    public function fill(Tenant $tenant, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $timezone = $tenant->plantTimezone();
        $from = CarbonImmutable::parse(CarbonImmutable::instance($from)->toDateString(), $timezone);
        $to = CarbonImmutable::parse(CarbonImmutable::instance($to)->toDateString(), $timezone)
            ->min(CarbonImmutable::now($timezone)->subDay()->startOfDay());

        if ($from->gt($to)) {
            return collect();
        }

        $workdays = $this->workdays($tenant, $from, $to);

        if ($workdays === []) {
            return collect();
        }

        return Employee::query()
            ->forTenant($tenant->id)
            ->active()
            ->where('area', self::AREA)
            ->orderBy('first_name')
            ->get()
            ->map(function (Employee $employee) use ($workdays, $from, $to, $timezone): ?array {
                $dates = $this->missingDates($employee, $workdays, $from, $to, $timezone);

                if ($dates === []) {
                    return null;
                }

                DB::transaction(function () use ($employee, $dates, $timezone): void {
                    foreach ($dates as $date) {
                        foreach (self::MARKS as [$time, $direction]) {
                            AttendanceScan::create([
                                'tenant_id' => $employee->tenant_id,
                                'employee_id' => $employee->id,
                                'scanned_at' => CarbonImmutable::parse("{$date} {$time}", $timezone)->utc(),
                                'direction' => $direction,
                                'source' => 'manual',
                                'notes' => self::NOTE,
                            ]);
                        }
                    }

                    $this->builder->buildForEmployee(
                        $employee,
                        CarbonImmutable::parse($dates[0], $timezone),
                        CarbonImmutable::parse(end($dates), $timezone),
                    );
                });

                return ['employee' => $employee, 'dates' => $dates];
            })
            ->filter()
            ->values();
    }

    /**
     * Los días laborales del rango en que rige la presunción: lunes a sábado, sin festivos
     * y fuera de una nómina cerrada.
     *
     * @return list<string>
     */
    private function workdays(Tenant $tenant, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $holidays = Holiday::query()
            ->forTenant($tenant->id)
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')
            ->map(fn ($date): string => CarbonImmutable::instance($date)->toDateString())
            ->flip();

        $closedRuns = PayrollRun::query()
            ->forTenant($tenant->id)
            ->where('status', PayrollRunStatus::Cerrada)
            ->get();

        return collect(CarbonPeriod::create($from, $to))
            ->map(fn (CarbonInterface $day): CarbonImmutable => CarbonImmutable::instance($day))
            ->reject(fn (CarbonImmutable $day): bool => $day->isSunday() || $holidays->has($day->toDateString()))
            ->reject(fn (CarbonImmutable $day): bool => $closedRuns->contains(fn (PayrollRun $run): bool => $day->betweenIncluded(
                CarbonImmutable::instance($run->hoursFrom())->startOfDay(),
                CarbonImmutable::instance($run->hoursTo())->endOfDay(),
            )))
            ->filter(fn (CarbonImmutable $day): bool => $this->parameters->isOn(PayrollParameter::PresumeAdministrativeWorkday, $day, $tenant->id))
            ->map(fn (CarbonImmutable $day): string => $day->toDateString())
            ->values()
            ->all();
    }

    /**
     * Los días laborales en que el trabajador no tiene nada: ni una marca —vigente o
     * anulada—, ni un día armado, ni una novedad, y en los que ya estaba contratado.
     *
     * @param  list<string>  $workdays
     * @return list<string>
     */
    private function missingDates(Employee $employee, array $workdays, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $scanned = AttendanceScan::query()
            ->withoutGlobalScope(AttendanceScan::VALID_SCOPE)
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [$from->startOfDay()->utc(), $to->endOfDay()->utc()])
            ->pluck('scanned_at')
            ->map(fn ($at): string => CarbonImmutable::instance($at)->setTimezone($timezone)->toDateString())
            ->flip();

        $built = AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->between($from->toDateString(), $to->toDateString())
            ->pluck('work_date')
            ->map(fn ($date): string => CarbonImmutable::instance($date)->toDateString())
            ->flip();

        $novelties = EmployeeNovelty::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->overlapping($from, $to)
            ->get(['starts_on', 'ends_on']);

        $hiredOn = $employee->hire_date?->toDateString();

        return array_values(array_filter($workdays, fn (string $date): bool => ! $scanned->has($date)
            && ! $built->has($date)
            && ($hiredOn === null || $date >= $hiredOn)
            && ! $novelties->contains(fn (EmployeeNovelty $n): bool => $date >= $n->starts_on->toDateString() && $date <= $n->ends_on->toDateString())));
    }
}
