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
use App\Models\Tenant;
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
 *
 * Con la jornada semanal encendida (`WeeklyOrdinaryHours` > 0) un día ya no se clasifica
 * solo: sus horas ordinarias son lo que le queda de la semana —lunes a domingo— después
 * de los días anteriores, y es extra únicamente lo que pasa de ese total. Por eso se
 * rehace hasta el domingo: una hora de más el lunes cambia el resto de la semana.
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
     * Con `$includeConfirmed` también rehace los días ya firmados —solo sus horas: la firma,
     * quién y cuándo, se conserva— y los ajustados a mano que tienen su entrada y su salida.
     * Es para recalcular un corte cuando cambia la regla, no para el día a día.
     *
     * @return Collection<int, AttendanceDay>
     */
    public function buildForEmployee(Employee $employee, CarbonInterface $from, CarbonInterface $to, bool $includeConfirmed = false): Collection
    {
        // Los días se cuentan en la hora de la planta, no en la UTC con que se guardan las
        // marcas: si no, la ventana nocturna y la medianoche quedan corridas cinco horas.
        $timezone = $this->plantTimezone($employee);
        $from = CarbonImmutable::parse(CarbonImmutable::instance($from)->toDateString(), $timezone)->startOfDay();
        $to = CarbonImmutable::parse(CarbonImmutable::instance($to)->toDateString(), $timezone)->endOfDay();

        if ($this->weeklyHours($employee->tenant_id, $from) > 0 || $this->weeklyHours($employee->tenant_id, $to) > 0) {
            $to = self::weekStart($to)->addDays(6)->endOfDay();
        }

        $surchargedDays = $this->surchargedDayResolver($employee->tenant_id, $from, $to);
        $restDays = $this->restDaysWorked($employee, $from, $to);

        $scans = AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [$from->subDays(self::LOOKBACK_DAYS)->utc(), $to->utc()])
            ->orderBy('scanned_at')
            ->get();

        [$sessionsByDate, $openEntries, $scheduledHours] = $this->pairIntoSessions($scans, $timezone);

        // Los días ya guardados desde el lunes de la primera semana: los de antes del rango
        // descuentan su parte de la semana, y los del rango que no se rehacen también.
        $stored = AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->between(self::weekStart($from)->toDateString(), $to->toDateString())
            ->get()
            ->keyBy(fn (AttendanceDay $day): string => $day->work_date->toDateString());

        $consumed = [];

        foreach ($stored as $date => $day) {
            if ($date < $from->toDateString()) {
                $week = self::weekStart(CarbonImmutable::parse($date))->toDateString();
                $consumed[$week] = ($consumed[$week] ?? 0.0) + self::ordinaryConsumed($day->hours());
            }
        }

        // El día que solo existe por la mirada hacia atrás no se escribe: su turno ya se
        // contabilizó en la reconstrucción del período anterior.
        $dates = collect(array_keys($sessionsByDate))
            ->merge($stored->keys())
            ->filter(fn (string $date): bool => $date >= $from->toDateString() && $date <= $to->toDateString())
            ->unique()
            ->sort()
            ->values();

        $built = collect();

        foreach ($dates as $workDate) {
            $date = CarbonImmutable::parse($workDate, $timezone);
            $week = self::weekStart($date)->toDateString();
            $weekly = $this->weeklyHours($employee->tenant_id, $date);
            $allowance = $weekly > 0 ? max(0.0, $weekly - ($consumed[$week] ?? 0.0)) : null;
            /** @var AttendanceDay|null $existing */
            $existing = $stored[$workDate] ?? null;
            $hours = $existing?->hours();

            if ($existing?->isManuallyAdjusted()) {
                // Lo ajustado a mano no lo pisa el reloj. Al recalcular la regla sí se
                // reclasifica, con la misma entrada y salida que escribió talento humano.
                if ($includeConfirmed && $existing->adjusted_entry_at && $existing->adjusted_exit_at) {
                    [$hours] = $this->classifyDay(
                        $employee,
                        $date,
                        [[CarbonImmutable::instance($existing->adjusted_entry_at)->setTimezone($timezone), CarbonImmutable::instance($existing->adjusted_exit_at)->setTimezone($timezone)]],
                        $surchargedDays,
                        (bool) $existing->rest_day_worked,
                        null,
                        $allowance,
                    );
                    $existing->update($hours->toArray() + ['worked_hours' => round($hours->workedHours(), 4)]);
                    $built->push($existing->refresh());
                }
            } elseif (array_key_exists($workDate, $sessionsByDate) && ($includeConfirmed || $existing?->status !== AttendanceDayStatus::Confirmada)) {
                [$hours, $anomalies] = $this->classifyDay(
                    $employee,
                    $date,
                    $sessionsByDate[$workDate],
                    $surchargedDays,
                    isset($restDays[$workDate]),
                    $openEntries[$workDate] ?? null,
                    // El turno que trae su fin de horario (el libro de horas extras) es
                    // ordinario hasta ahí; los demás, con la jornada.
                    $scheduledHours[$workDate] ?? $allowance,
                );

                $day = $this->persist($employee, $workDate, $hours, $anomalies, $includeConfirmed);

                if ($day) {
                    $built->push($day);
                }
            }

            if ($hours) {
                $consumed[$week] = ($consumed[$week] ?? 0.0) + self::ordinaryConsumed($hours);
            }
        }

        return $built;
    }

    /** El lunes de la semana del día: la semana de la jornada va de lunes a domingo. */
    public static function weekStart(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::instance($date)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    /** Lo que el día gastó de la jornada de la semana: lo trabajado que no fue extra. */
    private static function ordinaryConsumed(ClassifiedHours $hours): float
    {
        return max(0.0, $hours->workedHours() - $hours->overtimeHours());
    }

    /** La jornada semanal vigente ese día; 0 si la empresa cuenta por día. */
    private function weeklyHours(string $tenantId, CarbonInterface $on): float
    {
        return $this->parameters->valueOrDefault(PayrollParameter::WeeklyOrdinaryHours, CarbonImmutable::instance($on), $tenantId);
    }

    /**
     * Lo que le queda de la jornada de la semana a un día, después de los días anteriores
     * que ya están guardados. Null si la empresa cuenta por día.
     */
    private function weeklyAllowance(Employee $employee, CarbonImmutable $day): ?float
    {
        $weekly = $this->weeklyHours($employee->tenant_id, $day);

        if ($weekly <= 0) {
            return null;
        }

        $consumed = AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', self::weekStart($day)->toDateString())
            ->whereDate('work_date', '<', $day->toDateString())
            ->get()
            ->sum(fn (AttendanceDay $d): float => self::ordinaryConsumed($d->hours()));

        return max(0.0, $weekly - $consumed);
    }

    /**
     * Las horas de un día a partir de una entrada y una salida escritas a mano: las mismas
     * reglas que el reloj —nocturnas, dominicales y festivos, jornada, descanso trabajado,
     * redondeo, cargos sin extras— para que el ajuste no se clasifique a ojo.
     */
    public function classifyTimes(Employee $employee, CarbonInterface $workDate, CarbonImmutable $entry, CarbonImmutable $exit, bool $isRestDay = false): ClassifiedHours
    {
        $timezone = $this->plantTimezone($employee);
        $day = CarbonImmutable::parse(CarbonImmutable::instance($workDate)->toDateString(), $timezone);

        [$hours] = $this->classifyDay(
            $employee,
            $day,
            [[$entry->setTimezone($timezone), $exit->setTimezone($timezone)]],
            $this->surchargedDayResolver($employee->tenant_id, $day, $day->addDay()),
            $isRestDay,
            null,
            $this->weeklyAllowance($employee, $day),
        );

        return $hours;
    }

    /**
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $sessions
     * @return array{0: ClassifiedHours, 1: array<int, string>}
     */
    private function classifyDay(Employee $employee, CarbonImmutable $workDate, array $sessions, callable $surchargedDays, bool $isRestDay, ?string $openEntryAt = null, ?float $ordinaryAllowance = null): array
    {
        // Los parámetros de ese día y no los del inicio del rango: una reconstrucción
        // que cruce el 15 de julio de 2026 cruza también el paso a la jornada de 42 h.
        $config = $this->resolveConfig($employee->tenant_id, $workDate);

        if ($config['roundToHour']) {
            $sessions = self::roundSessions($sessions);
        }

        $hours = $this->classifier->classify(
            $sessions,
            $surchargedDays,
            $config['nightStart'],
            $config['nightEnd'],
            // El descanso trabajado no tiene jornada ordinaria: todo lo que se trabajó
            // ese día es extra. Con jornada semanal, lo ordinario es lo que le queda a la
            // semana; si no, la jornada diaria.
            $isRestDay ? 0.0 : ($ordinaryAllowance ?? $config['ordinaryPerDay']),
        );

        $anomalies = $this->anomaliesFor($hours, $config, $openEntryAt, $isRestDay);

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

        return [$hours, $anomalies];
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
     * @return array{0: array<string, array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>>, 1: array<string, string>, 2: array<string, float>}
     */
    private function pairIntoSessions(Collection $scans, string $timezone): array
    {
        $local = fn (AttendanceScan $scan): CarbonImmutable => CarbonImmutable::instance($scan->scanned_at)->setTimezone($timezone);
        $sessionsByDate = [];
        $openEntries = [];
        $scheduledHours = [];
        $openScan = null;

        foreach ($scans as $scan) {
            if ($scan->direction === AttendanceDirection::Entrada) {
                if ($openScan) {
                    // Dos entradas seguidas: la primera nunca se cerró. `AttendanceService`
                    // ya lo anota en la marca; aquí se traduce en un día sin horas.
                    $openEntries[$local($openScan)->toDateString()] = $local($openScan)->format('d/m/Y h:i a');
                    $sessionsByDate[$local($openScan)->toDateString()] ??= [];
                }

                $openScan = $scan;

                continue;
            }

            if (! $openScan) {
                // Salida sin entrada: la entrada quedó fuera del rango, o falta.
                continue;
            }

            $date = $local($openScan)->toDateString();
            $sessionsByDate[$date][] = [$local($openScan), $local($scan)];

            // La entrada que trae el fin de su horario: lo ordinario del día llega hasta ahí.
            if ($openScan->scheduled_end_at && ! isset($scheduledHours[$date])) {
                $end = CarbonImmutable::instance($openScan->scheduled_end_at)->setTimezone($timezone);

                if ($end->gt($local($openScan))) {
                    $scheduledHours[$date] = round($local($openScan)->diffInMinutes($end) / 60, 4);
                }
            }

            $openScan = null;
        }

        if ($openScan) {
            $date = $local($openScan)->toDateString();
            $openEntries[$date] = $local($openScan)->format('d/m/Y h:i a');
            $sessionsByDate[$date] ??= [];
        }

        ksort($sessionsByDate);

        return [$sessionsByDate, $openEntries, $scheduledHours];
    }

    /** @return array<int, string> */
    private function anomaliesFor(ClassifiedHours $hours, array $config, ?string $openEntryAt, bool $isRestDay = false): array
    {
        $anomalies = [];

        if ($openEntryAt !== null) {
            $anomalies[] = "Entrada del {$openEntryAt} sin salida registrada. Las horas de ese turno no se contaron.";
        }

        $overtime = $hours->overtimeHours();

        // Pasar del tope solo es un aviso cuando nada lo resuelve: con la regla del bono el
        // exceso ya tiene a dónde ir, y el descanso trabajado es extra entero por definición.
        if ($overtime > $config['maxOvertimeDay'] && ! $config['excessAsBonus'] && ! $isRestDay) {
            $anomalies[] = sprintf(
                'Horas extras del día: %s. El tope legal es %s.',
                number_format($overtime, 2, ',', '.'),
                number_format($config['maxOvertimeDay'], 2, ',', '.'),
            );
        }

        return $anomalies;
    }

    /**
     * Escribe la propuesta. Devuelve null si la fila ya estaba confirmada, salvo que se
     * pida recalcular las confirmadas: entonces cambian sus horas y la firma se queda.
     */
    private function persist(Employee $employee, string $workDate, ClassifiedHours $hours, array $anomalies, bool $includeConfirmed = false): ?AttendanceDay
    {
        return DB::transaction(function () use ($employee, $workDate, $hours, $anomalies, $includeConfirmed): ?AttendanceDay {
            $existing = AttendanceDay::query()
                ->forTenant($employee->tenant_id)
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->first();

            // Lo que talento humano ajustó a mano no se toca: el reloj lo pisaría en la
            // próxima marca o reconstrucción. Lo firmado tampoco, salvo al recalcular.
            if ($existing && $existing->isManuallyAdjusted()) {
                return null;
            }

            if ($existing && $existing->status === AttendanceDayStatus::Confirmada) {
                if (! $includeConfirmed) {
                    return null;
                }

                $existing->update($hours->toArray() + [
                    'worked_hours' => round($hours->workedHours(), 4),
                    'anomalies' => $anomalies ?: null,
                ]);

                return $existing->refresh();
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

    private function plantTimezone(Employee $employee): string
    {
        return Tenant::query()->whereKey($employee->tenant_id)->first()?->plantTimezone() ?? 'America/Bogota';
    }

    /** @return array{nightStart: float, nightEnd: float, ordinaryPerDay: float, maxOvertimeDay: float, excessAsBonus: bool} */
    private function resolveConfig(string $tenantId, CarbonImmutable $on): array
    {
        return [
            'nightStart' => $this->parameters->valueOn(PayrollParameter::NightWindowStart, $on, $tenantId),
            'nightEnd' => $this->parameters->valueOn(PayrollParameter::NightWindowEnd, $on, $tenantId),
            'ordinaryPerDay' => $this->parameters->valueOn(PayrollParameter::OrdinaryHoursPerDay, $on, $tenantId),
            'maxOvertimeDay' => $this->parameters->valueOn(PayrollParameter::MaxOvertimeHoursDay, $on, $tenantId),
            'excessAsBonus' => $this->parameters->isOn(PayrollParameter::OvertimeExcessAsBonus, $on, $tenantId),
            'roundToHour' => $this->parameters->isOn(PayrollParameter::RoundMarksToHour, $on, $tenantId),
        ];
    }

    /**
     * La hora en punto más cercana, como la cuenta la planta: hasta el minuto 30 baja
     * (5:30 → 5:00) y desde el 31 sube (5:31 → 6:00). Los segundos no cuentan. Igual para
     * la entrada y la salida, y para quien llega antes (4:40 → 5:00).
     */
    public static function roundToHour(CarbonImmutable $time): CarbonImmutable
    {
        $hour = $time->startOfHour();

        return $time->minute <= 30 ? $hour : $hour->addHour();
    }

    /**
     * Las sesiones con la entrada y la salida redondeadas. Una sesión que al redondear
     * queda sin tiempo —entró 5:10 y salió 5:25— no suma horas y se descarta.
     *
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $sessions
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function roundSessions(array $sessions): array
    {
        $rounded = array_map(
            fn (array $session): array => [self::roundToHour($session[0]), self::roundToHour($session[1])],
            $sessions,
        );

        return array_values(array_filter($rounded, fn (array $session): bool => $session[1]->gt($session[0])));
    }

    /**
     * Los días del rango que talento humano marcó como descanso trabajado. La marca vive
     * en el día y sobrevive a cada reconstrucción: el reloj no la puede deducir.
     *
     * @return array<string, true>
     */
    private function restDaysWorked(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->between($from->toDateString(), $to->toDateString())
            ->where('rest_day_worked', true)
            ->pluck('work_date')
            ->mapWithKeys(fn ($date): array => [$date->toDateString() => true])
            ->all();
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
