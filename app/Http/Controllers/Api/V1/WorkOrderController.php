<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Concerns\ProcessesBulkActions;
use App\Http\Controllers\Concerns\SortsApiQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkOrderRequest;
use App\Http\Requests\Api\V1\UpdateWorkOrderStatusRequest;
use App\Http\Resources\Api\V1\WorkOrderResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    use ProcessesBulkActions;
    use SortsApiQueries;

    public function __construct(private readonly WorkOrderService $service) {}

    public function mine(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('work-orders.read') && ! $request->user()->tokenCan('*'), 403);

        $query = WorkOrder::query()
            ->whereHas('technicians', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['equipment'])
            ->when($request->status, fn ($q, $v) => $q->whereIn('status', $this->statusList($v)))
            ->orderByDesc('created_at')
            ->orderBy('id');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return WorkOrderResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('work-orders.read') && ! $request->user()->tokenCan('*'), 403);

        $query = WorkOrder::query()
            ->with(['equipment'])
            ->when($request->status, fn ($q, $v) => $q->whereIn('status', $this->statusList($v)))
            ->when($request->equipment_id, fn ($q, $v) => $q->where('equipment_id', $v))
            ->when($request->type, fn ($q, $v) => $q->where('work_order_type', $v))
            ->when($request->from, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->to, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $like = '%'.$v.'%';
                $sub->where('work_order_number', 'ILIKE', $like)
                    ->orWhere('title', 'ILIKE', $like)
                    ->orWhere('description', 'ILIKE', $like);
            }));

        $this->applySort($query, $request, ['created_at', 'priority', 'status', 'work_order_number'], 'created_at', 'desc');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return WorkOrderResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): WorkOrderResource
    {
        abort_if(! $request->user()->tokenCan('work-orders.read') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::with([
            'equipment',
            'plant',
            'area',
            'technicians.user',
            'comments.user',
            'parts',
            'assignedSupervisor',
            'createdBy',
            'maintenanceRequest',
            'maintenancePlan.tasks',
            // Evidence Zone needs these up front — no more lazy per-tab
            // fetches for photos/signatures now that they live in one space.
            'attachments',
            'signatures.user',
        ])->findOrFail($id);

        $resource = new WorkOrderResource($workOrder);
        $resource->includeMission = true;

        return $resource;
    }

    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = $this->service->create(
            array_merge($request->validated(), ['tenant_id' => CurrentTenant::id()]),
            $request->user(),
        );

        $workOrder->load('equipment');

        return (new WorkOrderResource($workOrder))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('work-orders.show', $workOrder->id));
    }

    public function bulk(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
            'action' => ['required', Rule::in(['close', 'cancel', 'set_priority', 'assign_technician'])],
            'value' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $action = $validated['action'];

        $priority = null;
        $technician = null;

        if ($action === 'set_priority') {
            $priority = WorkOrderPriority::tryFrom((string) ($validated['value'] ?? ''));
            abort_if($priority === null, 422, 'Prioridad inválida.');
        }

        if ($action === 'assign_technician') {
            $technician = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', CurrentTenant::id()))
                ->find($validated['value'] ?? '');
            abort_if($technician === null, 422, 'Técnico inválido o ajeno al tenant.');
        }

        $result = $this->runBulk(
            $validated['ids'],
            fn (string $id) => WorkOrder::findOrFail($id),
            function (WorkOrder $workOrder) use ($action, $user, $priority, $technician): void {
                match ($action) {
                    'close' => $this->bulkTransition($workOrder, WorkOrderStatus::Closed, 'close', $user),
                    'cancel' => $this->bulkTransition($workOrder, WorkOrderStatus::Cancelled, 'update', $user),
                    'set_priority' => $this->bulkUpdate($workOrder, $user, fn () => $this->service->changePriority($workOrder, $priority)),
                    'assign_technician' => $this->bulkUpdate($workOrder, $user, fn () => $this->service->assignTechnician($workOrder, $technician, 'technician')),
                };
            },
        );

        return response()->json($result);
    }

    private function bulkTransition(WorkOrder $workOrder, WorkOrderStatus $to, string $ability, User $user): void
    {
        Gate::forUser($user)->authorize($ability, $workOrder);
        $this->service->transition($workOrder, $to, $user);
    }

    private function bulkUpdate(WorkOrder $workOrder, User $user, callable $apply): void
    {
        Gate::forUser($user)->authorize('update', $workOrder);
        $apply();
    }

    public function updateStatus(UpdateWorkOrderStatusRequest $request, string $id): WorkOrderResource
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($id);
        $toStatus = WorkOrderStatus::from($request->validated('status'));

        // Completion Experience data — only the fields actually sent, so a
        // plain "Pausar"/"Iniciar" transition never blanks out existing text.
        $extra = array_filter(
            $request->only(['work_performed', 'failure_cause', 'root_cause']),
            fn ($value) => $value !== null,
        );

        try {
            $workOrder = $this->service->transition($workOrder, $toStatus, $request->user(), $extra, $request->validated('gps'));
        } catch (\RuntimeException $e) {
            throw new BusinessRuleException($e->getMessage());
        }

        $workOrder->load(
            'equipment', 'assignedSupervisor', 'createdBy', 'maintenanceRequest', 'maintenancePlan.tasks',
            'attachments', 'signatures.user',
        );

        $resource = new WorkOrderResource($workOrder);
        $resource->includeMission = true;

        return $resource;
    }
}
