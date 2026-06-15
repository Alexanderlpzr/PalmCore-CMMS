<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Concerns\SortsApiQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkOrderRequest;
use App\Http\Requests\Api\V1\UpdateWorkOrderStatusRequest;
use App\Http\Resources\Api\V1\WorkOrderResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkOrderController extends Controller
{
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
        ])->findOrFail($id);

        return new WorkOrderResource($workOrder);
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

    public function updateStatus(UpdateWorkOrderStatusRequest $request, string $id): WorkOrderResource
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($id);
        $toStatus = WorkOrderStatus::from($request->validated('status'));

        try {
            $workOrder = $this->service->transition($workOrder, $toStatus, $request->user(), [], $request->validated('gps'));
        } catch (\RuntimeException $e) {
            throw new BusinessRuleException($e->getMessage());
        }

        $workOrder->load('equipment');

        return new WorkOrderResource($workOrder);
    }
}
