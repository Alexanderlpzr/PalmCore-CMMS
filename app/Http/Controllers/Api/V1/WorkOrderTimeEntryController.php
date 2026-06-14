<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Services\WorkOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkOrderTimeEntryRequest;
use App\Http\Resources\Api\V1\WorkOrderTimeEntryResource;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class WorkOrderTimeEntryController extends Controller
{
    public function __construct(private readonly WorkOrderService $service) {}

    public function store(StoreWorkOrderTimeEntryRequest $request, string $workOrder): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($workOrder);

        $timeLog = $this->service->logTime(
            $workOrder,
            $request->user(),
            Carbon::parse($request->validated('started_at')),
            $request->filled('ended_at') ? Carbon::parse($request->validated('ended_at')) : null,
            $request->validated('description'),
            $request->validated('gps'),
        );

        return (new WorkOrderTimeEntryResource($timeLog))
            ->response()
            ->setStatusCode(201);
    }
}
