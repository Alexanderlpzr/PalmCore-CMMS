<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Services\WorkOrderTaskService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RecordChecklistResultRequest;
use App\Http\Requests\Api\V1\SkipWorkOrderTaskRequest;
use App\Http\Requests\Api\V1\StoreWorkOrderTaskRequest;
use App\Http\Resources\Api\V1\WorkOrderChecklistResultResource;
use App\Http\Resources\Api\V1\WorkOrderTaskResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistResult;
use App\Models\WorkOrderTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The work itself, as the técnico sees it on his phone: the task list of an OT
 * and the checklist under each task.
 */
class WorkOrderTaskController extends Controller
{
    public function __construct(private readonly WorkOrderTaskService $service) {}

    public function index(Request $request, string $workOrder): JsonResponse
    {
        $this->authorizeRead($request);

        $workOrder = WorkOrder::findOrFail($workOrder);

        $tasks = $workOrder->tasks()
            ->with(['checklistResults' => fn ($q) => $q->orderBy('sort_order'), 'completedBy'])
            ->get();

        return response()->json([
            'data' => WorkOrderTaskResource::collection($tasks)->resolve(),
            'meta' => [
                'progress' => $this->service->progress($workOrder),
                'missing_required' => $this->service->missingRequiredResults($workOrder),
            ],
        ]);
    }

    public function store(StoreWorkOrderTaskRequest $request, string $workOrder): JsonResponse
    {
        $this->authorizeWrite($request);

        $workOrder = WorkOrder::findOrFail($workOrder);

        $task = $this->service->addTask($workOrder, $request->validated());

        return (new WorkOrderTaskResource($task->load('checklistResults')))
            ->response()
            ->setStatusCode(201);
    }

    public function start(Request $request, string $workOrder, string $task): WorkOrderTaskResource
    {
        $this->authorizeWrite($request);

        $task = $this->findTask($workOrder, $task);

        return new WorkOrderTaskResource(
            $this->service->startTask($task, $request->user())->load('checklistResults')
        );
    }

    /** Throws ChecklistIncompleteException (409) when a required item has no value. */
    public function complete(Request $request, string $workOrder, string $task): WorkOrderTaskResource
    {
        $this->authorizeWrite($request);

        $task = $this->findTask($workOrder, $task);

        return new WorkOrderTaskResource(
            $this->service->completeTask($task, $request->user())->load('checklistResults')
        );
    }

    public function skip(SkipWorkOrderTaskRequest $request, string $workOrder, string $task): WorkOrderTaskResource
    {
        $this->authorizeWrite($request);

        $task = $this->findTask($workOrder, $task);

        return new WorkOrderTaskResource(
            $this->service->skipTask($task, $request->user(), $request->validated('reason'))
                ->load('checklistResults')
        );
    }

    /** Record one checklist answer. An out-of-range value raises an alert. */
    public function recordResult(
        RecordChecklistResultRequest $request,
        string $workOrder,
        string $task,
        string $result,
    ): WorkOrderChecklistResultResource {
        $this->authorizeWrite($request);

        $task = $this->findTask($workOrder, $task);

        $result = WorkOrderChecklistResult::where('work_order_task_id', $task->id)
            ->findOrFail($result);

        return new WorkOrderChecklistResultResource(
            $this->service->recordChecklistResult($result, $request->user(), [
                'value' => $request->validated('value'),
                'notes' => $request->validated('notes'),
                'photo' => $request->file('photo'),
            ])
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function findTask(string $workOrderId, string $taskId): WorkOrderTask
    {
        return WorkOrderTask::where('work_order_id', $workOrderId)->findOrFail($taskId);
    }

    private function authorizeRead(Request $request): void
    {
        abort_if(
            ! $request->user()->tokenCan('work-orders.read') && ! $request->user()->tokenCan('*'),
            403,
        );
    }

    private function authorizeWrite(Request $request): void
    {
        abort_if(
            ! $request->user()->tokenCan('work-orders.write') && ! $request->user()->tokenCan('*'),
            403,
        );
    }
}
