<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Concerns\ProcessesBulkActions;
use App\Http\Controllers\Concerns\SortsApiQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMaintenanceRequestRequest;
use App\Http\Requests\Api\V1\UpdateMaintenanceRequestStatusRequest;
use App\Http\Resources\Api\V1\MaintenanceRequestResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaintenanceRequestController extends Controller
{
    use ProcessesBulkActions;
    use SortsApiQueries;

    public function __construct(private readonly MaintenanceRequestService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('maintenance-requests.read') && ! $request->user()->tokenCan('*'), 403);

        $query = MaintenanceRequest::query()
            ->with(['equipment'])
            ->when($request->status, fn ($q, $v) => $q->whereIn('status', $this->statusList($v)))
            ->when($request->equipment_id, fn ($q, $v) => $q->where('equipment_id', $v))
            ->orderByDesc('created_at');

        $perPage = min((int) ($request->per_page ?? 25), 200);

        return MaintenanceRequestResource::collection(
            $request->has('page')
                ? $query->paginate($perPage)
                : $query->cursorPaginate($perPage)
        );
    }

    public function show(Request $request, string $id): MaintenanceRequestResource
    {
        abort_if(! $request->user()->tokenCan('maintenance-requests.read') && ! $request->user()->tokenCan('*'), 403);

        $maintenanceRequest = MaintenanceRequest::with([
            'equipment',
            'comments.user',
            'workOrder',
        ])->findOrFail($id);

        return new MaintenanceRequestResource($maintenanceRequest);
    }

    public function store(StoreMaintenanceRequestRequest $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('maintenance-requests.write') && ! $request->user()->tokenCan('*'), 403);

        $maintenanceRequest = $this->service->create(
            array_merge($request->validated(), ['tenant_id' => CurrentTenant::id()]),
            $request->user(),
        );

        $maintenanceRequest->load('equipment');

        return (new MaintenanceRequestResource($maintenanceRequest))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('maintenance-requests.show', $maintenanceRequest->id));
    }

    public function bulk(Request $request): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('maintenance-requests.write') && ! $request->user()->tokenCan('*'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['uuid'],
            'action' => ['required', Rule::in(['approve', 'reject', 'set_priority'])],
            'value' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $action = $validated['action'];

        $priority = null;
        if ($action === 'set_priority') {
            $priority = MaintenanceRequestPriority::tryFrom((string) ($validated['value'] ?? ''));
            abort_if($priority === null, 422, 'Prioridad inválida.');
        }

        $result = $this->runBulk(
            $validated['ids'],
            fn (string $id) => MaintenanceRequest::findOrFail($id),
            function (MaintenanceRequest $mr) use ($action, $user, $priority): void {
                match ($action) {
                    'approve' => $this->bulkTransition($mr, MaintenanceRequestStatus::Approved, 'approve', $user),
                    'reject' => $this->bulkTransition($mr, MaintenanceRequestStatus::Rejected, 'approve', $user),
                    'set_priority' => $this->bulkSetPriority($mr, $user, $priority),
                };
            },
        );

        return response()->json($result);
    }

    private function bulkTransition(MaintenanceRequest $mr, MaintenanceRequestStatus $to, string $ability, User $user): void
    {
        Gate::forUser($user)->authorize($ability, $mr);
        $this->service->transition($mr, $to, $user);
    }

    private function bulkSetPriority(MaintenanceRequest $mr, User $user, MaintenanceRequestPriority $priority): void
    {
        Gate::forUser($user)->authorize('update', $mr);
        $this->service->changePriority($mr, $priority);
    }

    public function updateStatus(UpdateMaintenanceRequestStatusRequest $request, string $id): MaintenanceRequestResource
    {
        abort_if(! $request->user()->tokenCan('maintenance-requests.write') && ! $request->user()->tokenCan('*'), 403);

        $maintenanceRequest = MaintenanceRequest::findOrFail($id);
        $toStatus = MaintenanceRequestStatus::from($request->validated('status'));

        try {
            $maintenanceRequest = $this->service->transition($maintenanceRequest, $toStatus, $request->user());
        } catch (\RuntimeException $e) {
            throw new BusinessRuleException($e->getMessage());
        }

        $maintenanceRequest->load('equipment');

        return new MaintenanceRequestResource($maintenanceRequest);
    }
}
