<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkOrderCommentRequest;
use App\Http\Resources\Api\V1\WorkOrderCommentResource;
use App\Models\WorkOrder;
use App\Services\ActivityLocationService;
use Illuminate\Http\JsonResponse;

class WorkOrderCommentController extends Controller
{
    public function __construct(private readonly ActivityLocationService $locationService) {}

    public function store(StoreWorkOrderCommentRequest $request, string $workOrder): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($workOrder);

        $comment = $workOrder->comments()->create([
            'tenant_id' => $workOrder->tenant_id,
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'is_internal' => $request->validated('is_internal', false),
        ]);

        $gps = $request->validated('gps');

        if ($gps !== null) {
            $this->locationService->record($workOrder->tenant_id, $request->user(), ActivityType::Comment, $comment->id, $gps);
        }

        return (new WorkOrderCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }
}
