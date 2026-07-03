<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Services\EquipmentService;
use App\Http\Controllers\Concerns\ProcessesBulkActions;
use App\Http\Controllers\Concerns\SortsApiQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Equipment;
use App\Models\EquipmentQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    use ProcessesBulkActions;
    use SortsApiQueries;

    public function __construct(private readonly EquipmentService $service) {}

    public function bulk(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.write') && ! $request->user()->tokenCan('*'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
            'action' => ['required', Rule::in(['set_status', 'set_criticality'])],
            'value' => ['required', 'string'],
        ]);

        $user = $request->user();
        $action = $validated['action'];

        $status = null;
        $criticality = null;

        if ($action === 'set_status') {
            $status = EquipmentStatus::tryFrom($validated['value']);
            abort_if($status === null, 422, 'Estado inválido.');
        } else {
            $criticality = EquipmentCriticality::tryFrom($validated['value']);
            abort_if($criticality === null, 422, 'Criticidad inválida.');
        }

        $result = $this->runBulk(
            $validated['ids'],
            fn (string $id) => Equipment::findOrFail($id),
            function (Equipment $equipment) use ($action, $user, $status, $criticality): void {
                Gate::forUser($user)->authorize('update', $equipment);

                $action === 'set_status'
                    ? $this->service->changeStatus($equipment, $status)
                    : $this->service->changeCriticality($equipment, $criticality);
            },
        );

        return response()->json($result);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $activeWorkOrderStatuses = ['draft', 'planned', 'in_progress', 'on_hold'];

        $query = Equipment::query()
            ->with(['plant', 'area', 'category', 'kpi'])
            ->withCount(['workOrders as active_work_orders_count' => fn ($q) => $q->whereIn('status', $activeWorkOrderStatuses)])
            ->withExists(['maintenancePlans as overdue_preventives_flag' => fn ($q) => $q->where('is_active', true)->whereHas('schedule', fn ($sq) => $sq->where('next_due_at', '<', now()))])
            ->when($request->status, fn ($q, $v) => $q->whereIn('status', $this->statusList($v)))
            ->when($request->plant_id, fn ($q, $v) => $q->where('plant_id', $v))
            ->when($request->area_id, fn ($q, $v) => $q->where('area_id', $v))
            ->when($request->criticality, fn ($q, $v) => $q->where('criticality', $v))
            ->when(
                $request->has('is_active'),
                fn ($q) => $q->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->search, fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $like = '%'.$v.'%';
                $sub->where('code', 'ILIKE', $like)
                    ->orWhere('name', 'ILIKE', $like)
                    ->orWhere('serial_number', 'ILIKE', $like);
            }))
            ->when($request->boolean('has_active_work_orders'), fn ($q) => $q->whereHas('workOrders', fn ($wq) => $wq->whereIn('status', $activeWorkOrderStatuses)))
            ->when($request->boolean('has_overdue_preventives'), fn ($q) => $q->whereHas('maintenancePlans', fn ($mp) => $mp->where('is_active', true)->whereHas('schedule', fn ($sq) => $sq->where('next_due_at', '<', now()))));

        $this->applySort($query, $request, ['code', 'name', 'criticality', 'created_at'], 'code');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return EquipmentResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): EquipmentResource
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $equipment = Equipment::with([
            'plant',
            'area',
            'category',
            'manufacturer',
            'supplier',
            'parent.parent.parent',
            'kpi',
            'primaryPhoto',
            'photos',
            'documents',
            'children.category',
            'children.primaryPhoto',
            'children.kpi',
            'children.lastWorkOrder',
            'children.maintenancePlans.schedule',
        ])->findOrFail($id);

        return new EquipmentResource($equipment);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('equipment.write') && ! $request->user()->tokenCan('*'), 403);

        $tenantId = CurrentTenant::id();

        $data = $request->validate([
            'parent_equipment_id' => [
                'required',
                'uuid',
                Rule::exists('equipment', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('equipment', 'code')->where('tenant_id', $tenantId)],
            'category_id' => ['nullable', 'uuid', Rule::exists('equipment_categories', 'id')->where('tenant_id', $tenantId)],
            'status' => ['nullable', Rule::enum(EquipmentStatus::class)],
            'criticality' => ['nullable', Rule::enum(EquipmentCriticality::class)],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $equipment = Equipment::create([
            ...$data,
            'tenant_id' => $tenantId,
            'status' => $data['status'] ?? EquipmentStatus::Active->value,
            'created_by' => $request->user()->id,
        ]);

        $equipment->load(['category', 'primaryPhoto']);

        return (new EquipmentResource($equipment))
            ->response()
            ->setStatusCode(201);
    }

    public function byQrToken(Request $request, string $qrToken): EquipmentResource
    {
        abort_if(! $request->user()->tokenCan('equipment.read') && ! $request->user()->tokenCan('*'), 403);

        $qrCode = EquipmentQrCode::where('qr_token', $qrToken)
            ->where('is_active', true)
            ->firstOrFail();

        $qrCode->recordScan();

        $equipment = Equipment::with(['plant', 'area', 'category'])->findOrFail($qrCode->equipment_id);

        return new EquipmentResource($equipment);
    }
}
