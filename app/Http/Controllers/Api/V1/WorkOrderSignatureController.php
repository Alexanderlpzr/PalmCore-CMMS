<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkOrderSignatureRequest;
use App\Http\Resources\Api\V1\WorkOrderSignatureResource;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkOrderSignatureController extends Controller
{
    public function __construct(private readonly WorkOrderService $service) {}

    public function index(Request $request, string $workOrder): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('work-orders.read') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($workOrder);

        return WorkOrderSignatureResource::collection(
            $workOrder->signatures()->with('user')->latest('signed_at')->get()
        );
    }

    public function store(StoreWorkOrderSignatureRequest $request, string $workOrder): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($workOrder);
        $type = WorkOrderSignatureType::from($request->validated('signature_type'));

        $signature = $this->service->addSignature(
            $workOrder,
            $request->user(),
            $type,
            $request->validated('notes'),
            $request->validated('gps'),
            $request->file('signature_image'),
        );

        return (new WorkOrderSignatureResource($signature))
            ->response()
            ->setStatusCode(201);
    }
}
