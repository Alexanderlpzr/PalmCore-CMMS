<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDayStatus;
use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Models\AttendanceDay;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Lo que talento humano corrige de la puerta: la marca que faltó y la que sobró.
 *
 * Ninguna corrección borra nada. La que falta entra como marca manual, con quién la
 * puso y por qué; la que sobra queda anulada con su motivo. En los dos casos el día se
 * vuelve a calcular en el acto, para que «Horas por confirmar» muestre ya lo corregido.
 *
 * Un día confirmado no se toca: primero se reabre. Así nadie cambia por debajo las
 * horas que otra persona ya firmó.
 */
class AttendanceCorrectionService
{
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
            'notes' => trim($reason),
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
            'void_reason' => trim($reason),
        ]);

        $this->rebuild($employee, $workDate, $this->local($employee, $scan->scanned_at));

        return $scan->refresh();
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
        $timezone = Tenant::query()->whereKey($employee->tenant_id)->first()?->plantTimezone() ?? 'America/Bogota';

        return CarbonImmutable::instance($at)->setTimezone($timezone);
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
        $from = $workDate->min($at->startOfDay());
        $to = $workDate->max($at->startOfDay());

        $built = $this->builder->buildForEmployee($employee, $from, $to);

        AttendanceDay::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', AttendanceDayStatus::Propuesta)
            ->whereKeyNot($built->map(fn (AttendanceDay $day): string => $day->getKey())->all())
            ->delete();
    }
}
