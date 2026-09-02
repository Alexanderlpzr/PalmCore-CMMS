<?php

namespace App\Domain\HumanResources\Services;

use App\Domain\HumanResources\Enums\AttendanceDirection;
use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Models\AttendanceScan;
use App\Models\Employee;
use App\Models\EmployeeQrCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que pasa cuando portería escanea un carné.
 *
 * El vigilante no elige entrada o salida. Con lluvia y quince personas en la puerta,
 * pedirle que además acierte el botón es garantizar el error, así que el sentido se
 * deduce del último movimiento de esa persona. Pero se guarda ya resuelto: dentro de un
 * año, con solo las marcas sueltas, nadie podría reconstruir qué se dedujo entonces.
 *
 * Las dos reglas de abajo existen porque son los dos errores que de verdad ocurren en
 * una puerta, y ninguno de los dos se arregla pidiéndole más cuidado a nadie.
 */
class AttendanceService
{
    /**
     * Dos escaneos del mismo carné dentro de este lapso son el mismo escaneo.
     *
     * La pantalla del celular tarda, el vigilante no ve confirmación y vuelve a pasar el
     * carné. Sin esto, la persona entra y sale en el mismo segundo y el día le queda en
     * cero horas.
     */
    private const DEBOUNCE_SECONDS = 90;

    /**
     * A partir de aquí, una entrada abierta dejó de ser un turno y pasó a ser un olvido.
     *
     * Si alguien no marcó la salida el viernes, su primer escaneo del lunes no puede
     * interpretarse como «salida»: sería una jornada de tres días. Pasado este lapso la
     * entrada vieja se da por no cerrada —queda anotada para que el supervisor la
     * resuelva— y el escaneo nuevo vuelve a ser una entrada.
     */
    private const MAX_OPEN_SHIFT_HOURS = 16;

    /**
     * Resuelve el carné. Solo trabajadores activos: el retirado conserva su historia pero
     * no vuelve a marcar.
     */
    public function resolveToken(string $token, string $tenantId): EmployeeQrCode
    {
        $qrCode = EmployeeQrCode::query()
            ->forTenant($tenantId)
            ->where('qr_token', $token)
            ->where('is_active', true)
            ->with('employee')
            ->first();

        if (! $qrCode || ! $qrCode->employee) {
            throw AttendanceException::unknownToken();
        }

        if (! $qrCode->employee->status->canClockIn()) {
            throw AttendanceException::inactiveEmployee(
                $qrCode->employee->fullName(),
                $qrCode->employee->status->label(),
            );
        }

        return $qrCode;
    }

    /**
     * Registra la marca y devuelve el escaneo resultante.
     *
     * Cuando el escaneo cae dentro del lapso de rebote devuelve el anterior sin crear
     * nada: para portería el resultado en pantalla es el mismo, y en la base no queda un
     * turno de cero minutos.
     */
    public function record(
        EmployeeQrCode $qrCode,
        ?string $recordedBy = null,
        ?string $gate = null,
        ?CarbonInterface $at = null,
        string $source = 'qr',
    ): AttendanceScan {
        // `preventLazyLoading` está activo fuera de producción: quien llame con un carné
        // recién construido no tiene por qué saber que hay que precargar el empleado.
        $qrCode->loadMissing('employee');

        $employee = $qrCode->employee;
        $at ??= now();

        return DB::transaction(function () use ($qrCode, $employee, $recordedBy, $gate, $at, $source): AttendanceScan {
            $previous = $this->lastScanFor($employee, $at);

            if ($previous && $previous->scanned_at->diffInSeconds($at) < self::DEBOUNCE_SECONDS) {
                return $previous;
            }

            [$direction, $note] = $this->inferDirection($previous, $at);

            $scan = AttendanceScan::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'employee_qr_code_id' => $qrCode->id,
                'scanned_at' => $at,
                'direction' => $direction,
                'source' => $source,
                'recorded_by' => $recordedBy,
                'gate' => $gate,
                'notes' => $note,
            ]);

            $qrCode->recordScan();

            return $scan;
        });
    }

    /**
     * Qué sentido le corresponde a este escaneo.
     *
     * @return array{0: AttendanceDirection, 1: ?string}
     */
    private function inferDirection(?AttendanceScan $previous, CarbonInterface $at): array
    {
        if (! $previous) {
            return [AttendanceDirection::Entrada, null];
        }

        if ($previous->direction === AttendanceDirection::Salida) {
            return [AttendanceDirection::Entrada, null];
        }

        $openHours = $previous->scanned_at->diffInHours($at);

        if ($openHours >= self::MAX_OPEN_SHIFT_HOURS) {
            return [
                AttendanceDirection::Entrada,
                sprintf(
                    'Entrada nueva: la anterior del %s quedó sin salida (%d horas abiertas).',
                    $previous->scanned_at->format('d/m/Y H:i'),
                    $openHours,
                ),
            ];
        }

        return [AttendanceDirection::Salida, null];
    }

    private function lastScanFor(Employee $employee, CarbonInterface $before): ?AttendanceScan
    {
        return AttendanceScan::query()
            ->forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('scanned_at', '<=', $before)
            ->orderByDesc('scanned_at')
            ->first();
    }

    /**
     * Las entradas que nunca se cerraron en un rango. Es la bandeja del supervisor.
     *
     * Se calcula y no se guarda porque el estado «abierto» solo existe hasta que alguien
     * marca la salida o corrige la marca: persistirlo obligaría a mantenerlo sincronizado
     * y a explicar por qué la tabla dice una cosa y los escaneos otra.
     *
     * @return Collection<int, AttendanceScan>
     */
    public function openEntries(string $tenantId, CarbonInterface $from, CarbonInterface $to)
    {
        return AttendanceScan::query()
            ->forTenant($tenantId)
            ->whereBetween('scanned_at', [$from, $to])
            ->orderBy('employee_id')
            ->orderBy('scanned_at')
            ->with('employee')
            ->get()
            ->groupBy('employee_id')
            ->flatMap(function ($scans) {
                $open = collect();

                foreach ($scans as $scan) {
                    if ($scan->direction === AttendanceDirection::Entrada) {
                        $open->push($scan);
                    } elseif ($open->isNotEmpty()) {
                        $open->pop();
                    }
                }

                return $open;
            })
            ->values();
    }
}
