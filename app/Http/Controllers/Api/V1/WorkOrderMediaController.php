<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkOrderMediaRequest;
use App\Http\Resources\Api\V1\WorkOrderAttachmentResource;
use App\Models\WorkOrder;
use App\Services\ActivityLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class WorkOrderMediaController extends Controller
{
    public function __construct(private readonly ActivityLocationService $locationService) {}

    public function store(StoreWorkOrderMediaRequest $request, string $workOrder): JsonResponse
    {
        abort_if(! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($workOrder);
        $file = $request->file('file');

        $path = Storage::disk('work_orders_private')->putFile(
            "work-orders/{$workOrder->id}/media",
            $file
        );

        $attachment = $workOrder->attachments()->create([
            'tenant_id' => $workOrder->tenant_id,
            'attachment_type' => $request->validated('attachment_type'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'caption' => $request->validated('caption'),
            'uploaded_by' => $request->user()->id,
        ]);

        $gps = $request->validated('gps');

        if ($gps !== null) {
            $this->locationService->record($workOrder->tenant_id, $request->user(), ActivityType::Photo, $attachment->id, $gps);
        }

        return (new WorkOrderAttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }
}
