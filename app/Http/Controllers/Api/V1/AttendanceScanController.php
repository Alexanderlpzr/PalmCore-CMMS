<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\HumanResources\Exceptions\AttendanceException;
use App\Domain\HumanResources\Services\AttendanceService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\AttendanceScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * La puerta. Portería escanea un carné y esto responde qué pasó.
 *
 * A diferencia del QR de equipos, que abre una página pública, este endpoint exige
 * sesión: quien tenga el token de un compañero podría marcarle la entrada. El carné
 * identifica, no autoriza.
 *
 * La respuesta está pensada para leerse de un vistazo en un celular, bajo el alero de la
 * portería: nombre, sentido y hora. Nunca el sueldo — portería tiene `employees.view`
 * pero no `employee-salaries.view`, y esa separación se respeta también aquí.
 */
class AttendanceScanController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * Registra una marca a partir del token del carné.
     */
    public function store(Request $request): JsonResponse
    {
        abort_if(
            ! $request->user()->tokenCan('attendance.write') && ! $request->user()->tokenCan('*'),
            403,
        );

        Gate::forUser($request->user())->authorize('create', AttendanceScan::class);

        $data = $request->validate([
            'qr_token' => ['required', 'uuid'],
            'gate' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', Rule::in(['qr', 'manual'])],
        ]);

        // El middleware `api.tenant` ya resolvió el tenant de la petición.
        $tenantId = CurrentTenant::id();

        try {
            $qrCode = $this->attendance->resolveToken($data['qr_token'], $tenantId);
        } catch (AttendanceException $e) {
            // 422 y no 404: el carné puede existir perfectamente y aun así no poder
            // marcar —el retirado es el caso— y portería necesita leer por qué.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $scan = $this->attendance->record(
            qrCode: $qrCode,
            recordedBy: $request->user()->id,
            gate: $data['gate'] ?? null,
            source: $data['source'] ?? 'qr',
        );

        $employee = $qrCode->employee;

        return response()->json([
            'data' => [
                'scan_id' => $scan->id,
                'employee' => [
                    'id' => $employee->id,
                    'full_name' => $employee->fullName(),
                    'document_number' => $employee->document_number,
                    'position' => $employee->position,
                ],
                'direction' => $scan->direction->value,
                'direction_label' => $scan->direction->label(),
                'scanned_at' => $scan->scanned_at->toIso8601String(),
                'gate' => $scan->gate,
                // Se le dice a portería, no se le esconde: si el turno anterior quedó
                // abierto, alguien tiene que resolverlo antes de liquidar el mes.
                'notice' => $scan->notes,
            ],
        ], 201);
    }

    /**
     * Las marcas del día, para que portería confirme lo que acaba de registrar.
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(
            ! $request->user()->tokenCan('attendance.read') && ! $request->user()->tokenCan('*'),
            403,
        );

        Gate::forUser($request->user())->authorize('viewAny', AttendanceScan::class);

        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($data['date']) ? Carbon::parse($data['date']) : now();

        $scans = AttendanceScan::query()
            ->on($date->toDateString())
            ->with('employee:id,first_name,last_name,document_number,position')
            ->orderByDesc('scanned_at')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $scans->map(fn (AttendanceScan $scan): array => [
                'scan_id' => $scan->id,
                'employee_name' => $scan->employee?->fullName(),
                'document_number' => $scan->employee?->document_number,
                'direction' => $scan->direction->value,
                'direction_label' => $scan->direction->label(),
                'scanned_at' => $scan->scanned_at->toIso8601String(),
                'gate' => $scan->gate,
            ])->all(),
        ]);
    }
}
