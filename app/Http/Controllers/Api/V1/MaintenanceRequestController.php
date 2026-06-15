<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMaintenanceRequestRequest;
use App\Http\Requests\Api\V1\UpdateMaintenanceRequestStatusRequest;
use App\Http\Resources\Api\V1\MaintenanceRequestResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\MaintenanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceRequestController extends Controller
{
    public function __construct(private readonly MaintenanceRequestService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('maintenance-requests.read') && ! $request->user()->tokenCan('*'), 403);

        $query = MaintenanceRequest::query()
            ->with(['equipment'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
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
