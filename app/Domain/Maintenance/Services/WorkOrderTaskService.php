<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Maintenance\Enums\MaintenanceChecklistItemType;
use App\Domain\Maintenance\Enums\WorkOrderTaskStatus;
use App\Domain\Maintenance\Exceptions\ChecklistIncompleteException;
use App\Domain\Maintenance\Exceptions\InvalidChecklistValueException;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanTask;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistResult;
use App\Models\WorkOrderTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Execution of the work itself: the tasks and their checklists.
 *
 * The plan is a *template*. The moment an OT is generated the template is copied
 * into `work_order_tasks` / `work_order_checklist_results` and frozen there: the
 * planner may rewrite the plan tomorrow, but what the técnico was asked to do —
 * and the tolerance his reading was judged against — must stay exactly as it was.
 */
class WorkOrderTaskService
{
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    // ── Generation ────────────────────────────────────────────────────────────

    /**
     * Copy a maintenance plan's tasks and checklist items onto a work order.
     *
     * Idempotent: an OT that already has tasks is left alone, so re-running the
     * PM generator can never duplicate the work.
     *
     * @return int Number of tasks copied.
     */
    public function copyFromPlan(WorkOrder $workOrder, MaintenancePlan $plan): int
    {
        if ($workOrder->tasks()->exists()) {
            return 0;
        }

        return DB::transaction(function () use ($workOrder, $plan): int {
            $planTasks = MaintenancePlanTask::withoutGlobalScopes()
                ->where('maintenance_plan_id', $plan->id)
                ->with(['checklistItems' => fn ($q) => $q->withoutGlobalScopes()])
                ->orderBy('sort_order')
                ->get();

            foreach ($planTasks as $planTask) {
                $task = WorkOrderTask::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'work_order_id' => $workOrder->id,
                    'maintenance_plan_task_id' => $planTask->id,
                    'sort_order' => $planTask->sort_order,
                    'title' => $planTask->title,
                    'description' => $planTask->description,
                    'estimated_minutes' => $planTask->estimated_minutes,
                    'status' => WorkOrderTaskStatus::Pending->value,
                ]);

                foreach ($planTask->checklistItems as $item) {
                    WorkOrderChecklistResult::create([
                        'tenant_id' => $workOrder->tenant_id,
                        'work_order_task_id' => $task->id,
                        'maintenance_checklist_item_id' => $item->id,
                        'sort_order' => $item->sort_order,
                        'label' => $item->label,
                        'item_type' => $item->item_type->value,
                        'unit' => $item->unit,
                        'expected_min' => $item->expected_min,
                        'expected_max' => $item->expected_max,
                        'is_required' => $item->is_required,
                    ]);
                }
            }

            return $planTasks->count();
        });
    }

    /** Add an ad-hoc task to an OT that did not come from a plan. */
    public function addTask(WorkOrder $workOrder, array $data): WorkOrderTask
    {
        return WorkOrderTask::create([
            'tenant_id' => $workOrder->tenant_id,
            'work_order_id' => $workOrder->id,
            'sort_order' => $data['sort_order'] ?? (int) $workOrder->tasks()->max('sort_order') + 1,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => WorkOrderTaskStatus::Pending->value,
        ]);
    }

    // ── Execution ─────────────────────────────────────────────────────────────

    public function startTask(WorkOrderTask $task, User $actor): WorkOrderTask
    {
        $task->update([
            'status' => WorkOrderTaskStatus::InProgress->value,
            'started_at' => $task->started_at ?? now(),
            'assigned_to' => $task->assigned_to ?? $actor->id,
        ]);

        return $task->refresh();
    }

    /**
     * Mark a task done. Every required checklist item must carry a value first —
     * a "done" preventive with unanswered measurements is exactly the free-text
     * closure this module exists to eliminate.
     *
     * @throws ChecklistIncompleteException
     */
    public function completeTask(WorkOrderTask $task, User $actor): WorkOrderTask
    {
        $missing = $task->missingRequiredResults();

        if ($missing > 0) {
            throw ChecklistIncompleteException::forTask($task, $missing);
        }

        $task->update([
            'status' => WorkOrderTaskStatus::Done->value,
            'started_at' => $task->started_at ?? now(),
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ]);

        return $task->refresh();
    }

    /**
     * Skip a task. A reason is mandatory — "no se hizo" without a why is how a
     * preventive program quietly rots.
     */
    public function skipTask(WorkOrderTask $task, User $actor, string $reason): WorkOrderTask
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Omitir una tarea requiere indicar el motivo.');
        }

        $task->update([
            'status' => WorkOrderTaskStatus::Skipped->value,
            'skipped_reason' => $reason,
            'completed_at' => now(),
            'completed_by' => $actor->id,
        ]);

        return $task->refresh();
    }

    // ── Checklist ─────────────────────────────────────────────────────────────

    /**
     * Record one checklist answer. The value is routed to the column matching the
     * item's frozen type; `is_out_of_range` is then computed by the database.
     *
     * @param  array{value?: mixed, notes?: ?string, photo?: mixed}  $data
     */
    public function recordChecklistResult(
        WorkOrderChecklistResult $result,
        User $actor,
        array $data,
    ): WorkOrderChecklistResult {
        $attributes = [
            'value_boolean' => null,
            'value_numeric' => null,
            'value_text' => null,
            'notes' => $data['notes'] ?? $result->notes,
            'recorded_at' => now(),
            'recorded_by' => $actor->id,
        ];

        $value = $data['value'] ?? null;

        match ($result->item_type) {
            MaintenanceChecklistItemType::Boolean => $attributes['value_boolean'] = $this->toBoolean($value),
            MaintenanceChecklistItemType::Numeric => $attributes['value_numeric'] = $this->requireNumeric($value, $result),
            MaintenanceChecklistItemType::Text => $attributes['value_text'] = $value === null ? null : (string) $value,
        };

        if (isset($data['photo']) && $data['photo'] !== null) {
            $attributes['photo_path'] = $this->storePhoto($result, $data['photo']);
        }

        $result->update($attributes);
        $result->refresh();

        // The generated column is now authoritative — read it, don't recompute it.
        if ($result->is_out_of_range) {
            $this->raiseDeviationAlert($result);
        }

        return $result;
    }

    /**
     * Every required item across the whole OT that still has no value.
     * Skipped tasks are excluded: their checklist was deliberately not executed.
     */
    public function missingRequiredResults(WorkOrder $workOrder): int
    {
        return WorkOrderChecklistResult::withoutGlobalScopes()
            ->where('is_required', true)
            ->whereNull('recorded_at')
            ->whereIn('work_order_task_id', WorkOrderTask::withoutGlobalScopes()
                ->where('work_order_id', $workOrder->id)
                ->where('status', '!=', WorkOrderTaskStatus::Skipped->value)
                ->select('id'))
            ->count();
    }

    /**
     * Guard for the Completed transition: no OT closes with unanswered required
     * measurements, and no task is left silently pending.
     *
     * @throws ChecklistIncompleteException
     */
    public function assertReadyToComplete(WorkOrder $workOrder): void
    {
        if (! $workOrder->tasks()->exists()) {
            return;
        }

        $unresolvedTasks = $workOrder->tasks()->unresolved()->count();

        if ($unresolvedTasks > 0) {
            throw ChecklistIncompleteException::forWorkOrderTasks($workOrder, $unresolvedTasks);
        }

        $missing = $this->missingRequiredResults($workOrder);

        if ($missing > 0) {
            throw ChecklistIncompleteException::forWorkOrder($workOrder, $missing);
        }
    }

    /** Progress for the móvil header: [done+skipped, total]. */
    public function progress(WorkOrder $workOrder): array
    {
        $total = $workOrder->tasks()->count();
        $resolved = $total - $workOrder->tasks()->unresolved()->count();

        return ['resolved' => $resolved, 'total' => $total];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * A multipart form sends "false" as a string, and (bool) "false" is true —
     * which would silently turn a failed inspection into a passed one.
     */
    private function toBoolean(mixed $value): bool
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return (bool) $value;
    }

    private function requireNumeric(mixed $value, WorkOrderChecklistResult $result): float
    {
        if (! is_numeric($value)) {
            throw InvalidChecklistValueException::expectedNumeric($result);
        }

        return (float) $value;
    }

    private function storePhoto(WorkOrderChecklistResult $result, mixed $photo): ?string
    {
        if ($photo instanceof UploadedFile) {
            return Storage::disk(private_files_disk())->putFile(
                "work-orders/{$result->task->work_order_id}/checklist",
                $photo
            );
        }

        return is_string($photo) && $photo !== '' ? $photo : null;
    }

    /**
     * A reading outside its tolerance is the earliest signal of a failure in
     * formation — surface it immediately instead of burying it in the OT history.
     */
    private function raiseDeviationAlert(WorkOrderChecklistResult $result): void
    {
        $task = $result->task;
        $workOrder = $task?->workOrder;

        if ($workOrder === null) {
            return;
        }

        $this->alertService->create(new CreateAlertData(
            tenantId: $result->tenant_id,
            severity: AlertSeverity::Warning,
            category: AlertCategory::Reliability,
            title: "Lectura fuera de rango: {$result->label}",
            message: sprintf(
                '%s registró %s en «%s» (rango esperado: %s). OT %s — %s.',
                $result->recordedBy?->name ?? 'Un técnico',
                $result->displayValue() ?? '—',
                $result->label,
                $result->expectedRangeLabel() ?? 'sin definir',
                $workOrder->work_order_number,
                $workOrder->equipment?->name ?? 'equipo',
            ),
            entityType: WorkOrder::class,
            entityId: $workOrder->id,
            metadata: [
                'work_order_id' => $workOrder->id,
                'work_order_number' => $workOrder->work_order_number,
                'equipment_id' => $workOrder->equipment_id,
                'checklist_result_id' => $result->id,
                'label' => $result->label,
                'value' => $result->value_numeric,
                'unit' => $result->unit,
                'expected_min' => $result->expected_min,
                'expected_max' => $result->expected_max,
                'deviation' => $result->deviation(),
            ],
        ));
    }
}
