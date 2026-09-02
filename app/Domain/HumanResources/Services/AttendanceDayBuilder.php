<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\DTOs\ClassifiedHours;
use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Convierte las marcas crudas de portería en días de trabajo con las horas clasificadas,
 * listos para que un supervisor los confirme.
 *
 * Nunca confirma nada por su cuenta. Escribe propuestas, y solo pisa propuestas: una fila
 * ya confirmada se respeta aunque lleguen marcas nuevas, porque borrarla eliminaría la
 * revisión de una persona. Cuando eso pasa, la reconstrucción lo reporta en vez de
 * hacerlo en silencio.
 *
 * Las anomalías se registran, no bloquean. Que alguien haya trabajado catorce horas
 * excede el tope legal, pero el trabajo ya ocurrió: no anotarlo no lo deshace, y no
 * pagarlo tampoco. Lo que corresponde es que quede escrito y que el supervisor lo vea
 * antes de firmar.
 */
class AttendanceDayBuilder
{
    /**
     * Un turno puede haber arrancado antes del período que se está reconstruyendo, así
     * que la búsqueda de marcas se abre un día hacia atrás. Sin esto, el turno de noche
     * del último día del mes anterior aparecería como una salida huérfana.
     */
    private const LOOKBACK_DAYS = 1;

    public function __construct(
        private readonly HourClassifier $classifier,
        private readonly PayrollParameterService $parameters,
    ) {}

    /**
     * Reconstruye los días de un empleado en un rango.
     *
     * @return Collection<int, AttendanceDay>
     */
    public function buildForEmployee(Employee $employee, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->endOfDay();

        $config = $this->resolveConfig($employee->tenant_id, $from);
        $surchargedDays = $this->surchargedDayResolver($employee->tenant_id, $from, $to);

        $scans = AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [$from->subDays(self::LOOKBACK_DAYS), $to])
            ->orderBy('scanned_at')
            ->get();

        [$sessionsByDate, $openEntries] = $this->pairIntoSessions($scans);

        $built = collect();

        foreach ($sessionsByDate as $workDate => $sessions) {
            // El día que solo existe por la mirada hacia atrás no se escribe: su turno ya
            // se contabilizó en la reconstrucción del período anterior.
            if ($workDate < $from->toDateString()) {
                continue;
            }

            $hours = $this->classifier->classify(
                $sessions,
                $surchargedDays,
                $config['nightStart'],
                $config['nightEnd'],
                $config['ordinaryPerDay'],
            );

            $anomalies = $this->anomaliesFor($hours, $config, $openEntries[$workDate] ?? null);

            // Dirección, confianza y manejo, o salario integral: trabajó las horas, pero
            // no causan recargo ni extra. Todo lo trabajado pasa a ordinario para que el
            // día siga cuadrando con lo que marcó el reloj.
            if (! $employee->earnsOvertime()) {
                $anomalies[] = sprintf(
                    'Sin recargos ni extras: %s no las causa. Se registraron %s horas trabajadas.',
                    $employee->salary_type === 'integral' ? 'el salario integral' : 'el cargo',
                    number_format($hours->workedHours(), 2, ',', '.'),
                );

                $hours = new ClassifiedHours(ordinary: $hours->workedHours());
            }

            $day = $this->persist($employee, $workDate, $hours, $anomalies);

            if ($day) {
                $built->push($day);
            }
        }

        return $built;
    }

    /**
     * Reconstruye el período completo de la empresa.
     *
     * @return Collection<int, AttendanceDay>
     */
    public function buildForTenant(string $tenantId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $employees = Employee::query()
            ->forTenant($tenantId)
            ->active()
            ->get();

        return $employees->flatMap(
            fn (Employee $employee): Collection => $this->buildForEmployee($employee, $from, $to),
        );
    }

    /**
     * Empareja entradas con salidas.
     *
     * Una entrada abre el turno y la siguiente salida lo cierra. La sesión se atribuye al
     * día en que **arrancó** —así cuenta el jornal el libro de Excel— aunque sus horas
     * después se repartan entre dos días.
     *
     * @return array{0: array<string, array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>>, 1: array<string, string>}
     */
    private function pairIntoSessions(Collection $scans): array
    {
        $sessionsByDate = [];
        $openEntries = [];
        $openScan = null;

        foreach ($scans as $scan) {
            if ($scan->direction === AttendanceDirection::Entrada) {
                if ($openScan) {
                    // Dos entradas seguidas: la primera nunca se cerró. `AttendanceService`
                    // ya lo anota en la marca; aquí se traduce en un día sin horas.
                    $openEntries[$openScan->scanned_at->toDateString()] = $openScan->scanned_at->format('d/m/Y H:i');
                    $sessionsByDate[$openScan->scanned_at->toDateString()] ??= [];
                }

                $openScan = $scan;

                continue;
            }

            if (! $openScan) {
                // Salida sin entrada: la entrada quedó fuera del rango, o falta.
                continue;
            }

            $date = $openScan->scanned_at->toDateString();
            $sessionsByDate[$date][] = [
                CarbonImmutable::instance($openScan->scanned_at),
                CarbonImmutable::instance($scan->scanned_at),
            ];

            $openScan = null;
        }

        if ($openScan) {
            $date = $openScan->scanned_at->toDateString();
            $openEntries[$date] = $openScan->scanned_at->format('d/m/Y H:i');
            $sessionsByDate[$date] ??= [];
        }

        ksort($sessionsByDate);

        return [$sessionsByDate, $openEntries];
    }

    /** @return array<int, string> */
    private function anomaliesFor(ClassifiedHours $hours, array $config, ?string $openEntryAt): array
    {
        $anomalies = [];

        if ($openEntryAt !== null) {
            $anomalies[] = "Entrada del {$openEntryAt} sin salida registrada. Las horas de ese turno no se contaron.";
        }

        $overtime = $hours->overtimeHours();

        if ($overtime > $config['maxOvertimeDay']) {
            $anomalies[] = sprintf(
                'Horas extras del día: %s. El tope legal es %s.',
                number_format($overtime, 2, ',', '.'),
                number_format($config['maxOvertimeDay'], 2, ',', '.'),
            );
        }

        return $anomalies;
    }

    /**
     * Escribe la propuesta. Devuelve null si la fila ya estaba confirmada.
     */
    private function persist(Employee $employee, string $workDate, ClassifiedHours $hours, array $anomalies): ?AttendanceDay
    {
        return DB::transaction(function () use ($employee, $workDate, $hours, $anomalies): ?AttendanceDay {
            $existing = AttendanceDay::query()
                ->forTenant($employee->tenant_id)
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->first();

            if ($existing && $existing->status === AttendanceDayStatus::Confirmada) {
                return null;
            }

            $attributes = $hours->toArray() + [
                'worked_hours' => round($hours->workedHours(), 4),
                'status' => AttendanceDayStatus::Propuesta,
                'built_at' => now(),
                'source' => 'qr',
                'anomalies' => $anomalies ?: null,
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing->refresh();
            }

            return AttendanceDay::create($attributes + [
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'work_date' => $workDate,
            ]);
        });
    }

    /**
     * Los días de una semana en que las extras pasaron del tope semanal.
     *
     * Va aparte del cálculo diario porque es la única regla que no se puede evaluar
     * mirando un día solo: doce horas extras repartidas en seis días no violan ningún
     * tope diario y sí el semanal.
     *
     * @return Collection<int, array{week: string, hours: float, limit: float, days: Collection<int, AttendanceDay>}>
     */
    public function weeklyOvertimeBreaches(string $tenantId, string $employeeId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $limit = $this->parameters->valueOn(PayrollParameter::MaxOvertimeHoursWeek, CarbonImmutable::instance($from), $tenantId);

        return AttendanceDay::query()
            ->forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->between(CarbonImmutable::instance($from)->toDateString(), CarbonImmutable::instance($to)->toDateString())
            ->orderBy('work_date')
            ->get()
            ->groupBy(fn (AttendanceDay $day): string => $day->work_date->format('o-\WW'))
            ->map(fn (Collection $days, string $week): array => [
                'week' => $week,
                'hours' => round($days->sum(fn (AttendanceDay $d): float => $d->hours()->overtimeHours()), 4),
                'limit' => $limit,
                'days' => $days,
            ])
            ->filter(fn (array $row): bool => $row['hours'] > $row['limit'])
            ->values();
    }

    /** @return array{nightStart: float, nightEnd: float, ordinaryPerDay: float, maxOvertimeDay: float} */
    private function resolveConfig(string $tenantId, CarbonImmutable $on): array
    {
        return [
            'nightStart' => $this->parameters->valueOn(PayrollParameter::NightWindowStart, $on, $tenantId),
            'nightEnd' => $this->parameters->valueOn(PayrollParameter::NightWindowEnd, $on, $tenantId),
            'ordinaryPerDay' => $this->parameters->valueOn(PayrollParameter::OrdinaryHoursPerDay, $on, $tenantId),
            'maxOvertimeDay' => $this->parameters->valueOn(PayrollParameter::MaxOvertimeHoursDay, $on, $tenantId),
        ];
    }

    /**
     * Domingos y festivos del rango, resueltos de una sola consulta.
     *
     * Se precargan porque el clasificador pregunta por cada tramo, y un mes de 48
     * personas son miles de preguntas que si no serían miles de consultas.
     */
    private function surchargedDayResolver(string $tenantId, CarbonImmutable $from, CarbonImmutable $to): callable
    {
        $holidays = Holiday::query()
            ->forTenant($tenantId)
            ->whereBetween('holiday_date', [
                $from->subDays(self::LOOKBACK_DAYS + 1)->toDateString(),
                $to->addDay()->toDateString(),
            ])
            ->pluck('holiday_date')
            ->map(fn ($date): string => CarbonImmutable::instance($date)->toDateString())
            ->flip();

        return fn (CarbonImmutable $date): bool => $date->isSunday()
            || $holidays->has($date->toDateString());
    }
}
