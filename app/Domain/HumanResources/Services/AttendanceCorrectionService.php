<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Enums\PayrollRunStatus;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que talento humano corrige de la puerta: la marca que faltó y la que sobró.
 *
 * Ninguna corrección borra nada. La que falta entra como marca manual, con quién la
 * puso y por qué; la que sobra queda anulada con su motivo. En los dos casos el día se
 * vuelve a calcular en el acto, para que «Horas por confirmar» muestre ya lo corregido.
 *
 * Desde «Horas por confirmar» también se corrige el día entero: la hora de sus marcas,
 * sus horas a mano —el día queda «ajustado a mano» y el reloj ya no lo recalcula— o
 * anularlo, que anula sus marcas para que no vuelva al reconstruir.
 *
 * Un día confirmado no se toca: primero se reabre. Así nadie cambia por debajo las
 * horas que otra persona ya firmó.
 */
class AttendanceCorrectionService
{
    /** Las ocho bolsas de horas de un día, en el orden en que se muestran. */
    public const HOUR_FIELDS = [
        'ordinary_hours',
        'night_surcharge_hours',
        'sunday_surcharge_hours',
        'night_sunday_surcharge_hours',
        'overtime_day_hours',
        'overtime_night_hours',
        'overtime_sunday_day_hours',
        'overtime_sunday_night_hours',
    ];

    public function __construct(
        private readonly AttendanceDayBuilder $builder,
    ) {}

    /**
     * Agrega la marca que no quedó: la salida olvidada, la entrada sin carné.
     */
    public function addManualMark(
        Employee $employee,
        CarbonInterface $at,
        AttendanceDirection $direction,
        string $reason,
        User $by,
    ): AttendanceScan {
        if ($at->isFuture()) {
            throw AttendanceException::markInTheFuture();
        }

        $workDate = $this->workDateFor($employee, $at, $direction);
        $this->ensureDayIsOpen($employee, $workDate);

        $scan = DB::transaction(fn (): AttendanceScan => AttendanceScan::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            // En UTC, como las de la puerta: Eloquent guarda la hora de pared del Carbon
            // sin convertirla, y una hora de Bogotá quedaría corrida cinco horas.
            'scanned_at' => CarbonImmutable::instance($at)->utc(),
            'direction' => $direction,
            'source' => 'manual',
            'recorded_by' => $by->id,
            'notes' => self::justification($reason),
        ]));

        $this->rebuild($employee, $workDate, $this->local($employee, $at));

        return $scan;
    }

    /**
     * Anula la marca equivocada: deja de contar, pero sigue a la vista.
     */
    public function voidMark(AttendanceScan $scan, string $reason, User $by): AttendanceScan
    {
        if ($scan->isVoided()) {
            throw AttendanceException::alreadyVoided();
        }

        $scan->loadMissing('employee');
        $employee = $scan->employee;
        $workDate = $this->workDateFor($employee, $scan->scanned_at, $scan->direction, ignoring: $scan);
        $this->ensureDayIsOpen($employee, $workDate);

        $scan->update([
            'voided_at' => now(),
            'voided_by' => $by->id,
            'void_reason' => self::justification($reason),
        ]);

        $this->rebuild($employee, $workDate, $this->local($employee, $scan->scanned_at));

        return $scan->refresh();
    }

    /**
     * Las marcas vigentes que forman un día, emparejadas como lo hace el reloj: las
     * entradas de esa fecha, la salida que cierra cada una aunque caiga al día siguiente
     * (el turno de noche) y las salidas sueltas de la fecha. La salida de la madrugada que
     * cierra el turno de la víspera no es de este día.
     *
     * @return Collection<int, AttendanceScan>
     */
    public function marksOfDay(AttendanceDay $day): Collection
    {
        $day->loadMissing('employee');
        $employee = $day->employee;
        $fecha = $day->work_date->toDateString();
        $inicio = CarbonImmutable::parse($fecha, $this->timezoneOf($employee))->startOfDay();

        $marcas = AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('scanned_at', [$inicio->subDay()->utc(), $inicio->addDay()->endOfDay()->utc()])
            ->orderBy('scanned_at')
            ->get();

        $delDia = collect();
        $entradaAbierta = null;

        foreach ($marcas as $marca) {
            $fechaDeLaMarca = $this->local($employee, $marca->scanned_at)->toDateString();

            if ($marca->isEntry()) {
                $entradaAbierta = $marca;

                if ($fechaDeLaMarca === $fecha) {
                    $delDia->push($marca);
                }

                continue;
            }

            if ($entradaAbierta !== null) {
                if ($this->local($employee, $entradaAbierta->scanned_at)->toDateString() === $fecha) {
                    $delDia->push($marca);
                }

                $entradaAbierta = null;

                continue;
            }

            if ($fechaDeLaMarca === $fecha) {
                $delDia->push($marca);
            }
        }

        return $delDia->values();
    }

    /**
     * Corrige la hora de las marcas de un día. Cada marca cambiada se anula y se reemplaza
     * por una a mano con la hora nueva, con el motivo en las dos: el rastro queda en
     * «Marcas de portería». Si el día tenía horas ajustadas a mano, vuelve a calcularse
     * desde las marcas, que es lo que se acaba de corregir.
     *
     * @param  array<string, CarbonInterface|string>  $times  id de la marca => hora nueva
     */
    public function editDayMarks(AttendanceDay $day, array $times, string $reason, User $by): AttendanceDay
    {
        $this->ensureProposed($day);
        $employee = $day->employee;
        $motivo = trim($reason);

        $cambios = $this->marksOfDay($day)
            ->filter(fn (AttendanceScan $marca): bool => isset($times[$marca->getKey()]))
            ->map(fn (AttendanceScan $marca): array => [$marca, CarbonImmutable::parse($times[$marca->getKey()])->utc()])
            ->filter(fn (array $par): bool => abs($par[0]->scanned_at->diffInSeconds($par[1])) >= 60);

        if ($cambios->isEmpty()) {
            throw AttendanceException::nothingChanged();
        }

        $fechas = collect([CarbonImmutable::parse($day->work_date->toDateString())]);

        foreach ($cambios as [$marca, $nueva]) {
            if ($nueva->isFuture()) {
                throw AttendanceException::markInTheFuture();
            }

            $local = $this->local($employee, $nueva)->startOfDay();

            if ($local->toDateString() !== $day->work_date->toDateString()) {
                $this->ensureDayIsOpen($employee, $local);
            }

            $fechas->push($local, $this->local($employee, $marca->scanned_at)->startOfDay());
        }

        DB::transaction(function () use ($cambios, $employee, $motivo, $by, $day): void {
            foreach ($cambios as [$marca, $nueva]) {
                $antes = $this->local($employee, $marca->scanned_at)->format('d/m/Y h:i a');
                $despues = $this->local($employee, $nueva)->format('d/m/Y h:i a');

                $marca->update([
                    'voided_at' => now(),
                    'voided_by' => $by->id,
                    'void_reason' => "Hora corregida a {$despues}".($motivo !== '' ? ": {$motivo}" : ''),
                ]);

                AttendanceScan::create([
                    'tenant_id' => $employee->tenant_id,
                    'employee_id' => $employee->id,
                    'scanned_at' => $nueva,
                    'direction' => $marca->direction,
                    'source' => 'manual',
                    'recorded_by' => $by->id,
                    'gate' => $marca->gate,
                    'notes' => "Corrige la marca de las {$antes}".($motivo !== '' ? ": {$motivo}" : ''),
                ]);
            }

            // Lo que se corrige son las marcas: el día vuelve a salir de ellas.
            $day->update(['source' => 'qr', 'adjusted_by' => null, 'adjusted_at' => null, 'adjustment_reason' => null]);
        });

        $this->rebuildRange($employee, $fechas->min(), $fechas->max());

        return $day->fresh() ?? $day;
    }

    /**
     * Ajusta a mano las horas de un día, sin tocar sus marcas: la extra que no se
     * autorizó, el turno que se paga distinto. El día queda «ajustado a mano», con quién y
     * por qué, y el reloj ya no lo recalcula hasta que se corrijan sus marcas.
     *
     * @param  array<string, float|int|string|null>  $hours  las ocho bolsas de ClassifiedHours::toArray()
     */
    public function adjustHours(AttendanceDay $day, array $hours, string $reason, User $by): AttendanceDay
    {
        // Un día confirmado también se ajusta —una novedad que llegó tarde— y sigue
        // confirmado; lo que no se toca es lo que ya se pagó en una nómina cerrada.
        $this->ensureEditable($day);

        $bolsas = [];

        foreach (self::HOUR_FIELDS as $campo) {
            $valor = (float) ($hours[$campo] ?? 0);

            if ($valor < 0) {
                throw AttendanceException::hoursOutOfRange();
            }

            $bolsas[$campo] = round($valor, 4);
        }

        $total = array_sum($bolsas);

        if ($total > 24) {
            throw AttendanceException::hoursOutOfRange();
        }

        $day->update([
            ...$bolsas,
            'worked_hours' => round($total, 4),
            'source' => 'manual',
            'adjusted_by' => $by->id,
            'adjusted_at' => now(),
            'adjustment_reason' => self::justification($reason),
        ]);

        return $day->refresh();
    }

    /**
     * Marca o desmarca el día como descanso trabajado: el domingo o festivo que alguien
     * vino a trabajar en su día libre. Marcado, todo lo trabajado ese día es extra y el
     * tope diario no lo parte; el día se vuelve a calcular desde sus marcas en el acto.
     *
     * Un día ajustado a mano conserva sus horas —no salen de las marcas— y solo cambia
     * la marca, que la liquidación sí mira.
     */
    public function setRestDayWorked(AttendanceDay $day, bool $restDayWorked): AttendanceDay
    {
        $this->ensureProposed($day);

        $day->update(['rest_day_worked' => $restDayWorked]);

        if (! $day->isManuallyAdjusted()) {
            $fecha = CarbonImmutable::parse($day->work_date->toDateString());
            $this->builder->buildForEmployee($day->employee, $fecha, $fecha);
        }

        return $day->refresh();
    }

    /**
     * Marca o desmarca el día como apoyo a mantenimiento. No cambia lo que se paga, solo
     * a qué grupo se cargan sus horas en el indicador «factor de horas»; por eso vale
     * también en un día ya confirmado.
     */
    public function setMaintenanceSupport(AttendanceDay $day, bool $maintenanceSupport): AttendanceDay
    {
        $day->update(['maintenance_support' => $maintenanceSupport]);

        return $day->refresh();
    }

    /**
     * Anula un día: anula sus marcas con el motivo y lo quita de «Horas por confirmar».
     * No se paga y no vuelve al reconstruir, porque ya no tiene marcas vigentes; el
     * rastro queda en «Marcas de portería», con quién y por qué.
     */
    public function voidDay(AttendanceDay $day, string $reason, User $by): void
    {
        $this->ensureEditable($day);
        $employee = $day->employee;
        $motivo = trim($reason);
        $fecha = CarbonImmutable::parse($day->work_date->toDateString());

        DB::transaction(function () use ($day, $by, $motivo): void {
            $this->marksOfDay($day)->each(fn (AttendanceScan $marca) => $marca->update([
                'voided_at' => now(),
                'voided_by' => $by->id,
                'void_reason' => 'Día anulado'.($motivo !== '' ? ": {$motivo}" : ''),
            ]));

            $day->delete();
        });

        // La víspera y el día siguiente pudieron emparejarse con estas marcas.
        $this->rebuildRange($employee, $fecha->subDay(), $fecha->addDay());
    }

    /**
     * El día al que pertenece una marca es el día en que arrancó su turno: la salida de
     * las seis de la mañana es del turno de noche que empezó la víspera.
     */
    private function workDateFor(
        Employee $employee,
        CarbonInterface $at,
        AttendanceDirection $direction,
        ?AttendanceScan $ignoring = null,
    ): CarbonImmutable {
        $at = $this->local($employee, $at);

        if ($direction === AttendanceDirection::Entrada) {
            return $at->startOfDay();
        }

        $entry = AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('direction', AttendanceDirection::Entrada)
            ->where('scanned_at', '<', $at)
            ->where('scanned_at', '>=', $at->subHours(AttendanceService::MAX_OPEN_SHIFT_HOURS))
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring->getKey()))
            ->orderByDesc('scanned_at')
            ->first();

        return ($entry ? $this->local($employee, $entry->scanned_at) : $at)->startOfDay();
    }

    /** La hora de la planta: ahí se decide a qué día pertenece una marca. */
    private function local(Employee $employee, CarbonInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->setTimezone($this->timezoneOf($employee));
    }

    private function timezoneOf(Employee $employee): string
    {
        return Tenant::query()->whereKey($employee->tenant_id)->first()?->plantTimezone() ?? 'America/Bogota';
    }

    /** Editar o anular un día exige que siga sin firmar: uno confirmado se reabre antes. */
    private function ensureProposed(AttendanceDay $day): void
    {
        $day->loadMissing('employee');

        if ($day->status === AttendanceDayStatus::Confirmada) {
            throw AttendanceException::dayAlreadyConfirmed($day->work_date->format('d/m/Y'));
        }
    }

    /**
     * Ajustar o anular vale con el día propuesto o confirmado, salvo que ya haya entrado a
     * una nómina cerrada: esa plata ya se pagó y se aportó.
     */
    /**
     * La justificación es opcional: si se escribe queda en el registro, y si no, el
     * cambio igual dice quién lo hizo y cuándo.
     */
    private static function justification(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }

    private function ensureEditable(AttendanceDay $day): void
    {
        $day->loadMissing('employee');
        $date = $day->work_date->toDateString();

        $closed = PayrollRun::query()
            ->forTenant($day->tenant_id)
            ->where('status', PayrollRunStatus::Cerrada)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereNotNull('hours_from')->whereDate('hours_from', '<=', $date)->whereDate('hours_to', '>=', $date))
                ->orWhere(fn ($q) => $q->whereNull('hours_from')->whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)))
            ->first();

        if ($closed) {
            throw AttendanceException::dayInClosedPayroll($day->work_date->format('d/m/Y'), $closed->name);
        }
    }

    private function ensureDayIsOpen(Employee $employee, CarbonImmutable $workDate): void
    {
        $confirmed = AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->where('status', AttendanceDayStatus::Confirmada)
            ->exists();

        if ($confirmed) {
            throw AttendanceException::dayAlreadyConfirmed($workDate->format('d/m/Y'));
        }
    }

    /**
     * Rehace los días que la marca pudo mover y quita las propuestas que se quedaron sin
     * marcas: si se anula la única entrada de un día, ese día ya no tiene horas.
     */
    private function rebuild(Employee $employee, CarbonImmutable $workDate, CarbonImmutable $at): void
    {
        $this->rebuildRange($employee, $workDate->min($at->startOfDay()), $workDate->max($at->startOfDay()));
    }

    /**
     * Rehace los días del rango y quita las propuestas que quedaron sin marcas. Un día
     * ajustado a mano no se quita: sus horas no salen de las marcas.
     */
    private function rebuildRange(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $built = $this->builder->buildForEmployee($employee, $from, $to);

        AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', AttendanceDayStatus::Propuesta)
            ->where('source', '!=', 'manual')
            ->whereKeyNot($built->map(fn (AttendanceDay $day): string => $day->getKey())->all())
            ->delete();
    }
}
