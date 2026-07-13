<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Notifications\WorkOrderAssignedNotification;
use App\Domain\Notifications\WorkOrderStatusChangedNotification;
use App\Domain\Shared\Enums\ActivityType;
use App\Events\WorkOrderCreated;
use App\Events\WorkOrderStatusChanged;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use App\Models\WorkOrderTechnician;
use App\Models\WorkOrderTimeLog;
use App\Services\ActivityLocationService;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkOrderService
{
    public function __construct(
        private readonly WorkOrderInventoryService $workOrderInventoryService,
        private readonly ActivityLocationService $locationService,
        private readonly WorkOrderTaskService $taskService,
    ) {}

    // ── Numbering ─────────────────────────────────────────────────────────────

    /**
     * Generate OT-{YEAR}-{EQUIPMENT_CODE}-{SEQUENTIAL}.
     * SEQUENTIAL is 6-digit, global per tenant per year.
     * Uses lockForUpdate() to prevent race conditions.
     */
    public function generateWorkOrderNumber(string $tenantId, string $equipmentCode): string
    {
        $year = date('Y');

        // orderByDesc on the full VARCHAR string produces wrong results when
        // equipment codes differ lexicographically (e.g. 'ZZZ' > 'AAA' pushes a
        // low-sequence ZZZ row above a high-sequence AAA row, making the extracted
        // suffix too small and generating a duplicate number). Cast the 6-digit
        // numeric suffix explicitly so the MAX is always the true global sequence.
        $last = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('work_order_number', 'like', "OT-{$year}-%")
            ->lockForUpdate()
            ->orderByRaw('CAST(RIGHT(work_order_number, 6) AS INTEGER) DESC')
            ->value('work_order_number');

        $sequence = 1;

        if ($last !== null) {
            $sequence = (int) substr($last, -6) + 1;
        }

        return sprintf('OT-%s-%s-%06d', $year, $equipmentCode, $sequence);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * Create a new WorkOrder.
     * Emergency type skips draft/planned and starts directly in_progress.
     * Syncs equipment status and creates a downtime event when applicable.
     */
    public function create(array $data, User $createdBy): WorkOrder
    {
        $workOrder = DB::transaction(function () use ($data, $createdBy): WorkOrder {
            $raw = $data['work_order_type'];
            $type = $raw instanceof WorkOrderType ? $raw : WorkOrderType::from($raw);
            $status = $type->startsInProgress()
                ? WorkOrderStatus::InProgress
                : WorkOrderStatus::Draft;

            $equipment = Equipment::withoutGlobalScopes()->findOrFail($data['equipment_id']);

            $number = $this->generateWorkOrderNumber($data['tenant_id'], $equipment->code);

            $workOrder = WorkOrder::create([
                ...$data,
                'work_order_number' => $number,
                'status' => $status->value,
                'plant_id' => $data['plant_id'] ?? $equipment->plant_id,
                'area_id' => $data['area_id'] ?? $equipment->area_id,
                'created_by' => $createdBy->id,
                'started_at' => $type->startsInProgress() ? now() : null,
                'actual_start_at' => $type->startsInProgress() ? now() : null,
            ]);

            // Born from a plan: the plan's tasks and checklist are copied in and
            // frozen now, so later edits to the template never rewrite this OT.
            if (! empty($data['maintenance_plan_id'])) {
                $plan = MaintenancePlan::withoutGlobalScopes()->find($data['maintenance_plan_id']);

                if ($plan !== null) {
                    $this->taskService->copyFromPlan($workOrder, $plan);
                }
            }

            // Emergency WOs start InProgress directly — trigger the same syncs a
            // transition would. Each self-guards: equipment status only moves when
            // the equipment was stopped, while the failure event is recorded for
            // any failure-type WO even if the machine kept running.
            if ($type->startsInProgress()) {
                $this->syncEquipmentStatus($workOrder, WorkOrderStatus::InProgress);
                $this->syncDowntimeEvent($workOrder, WorkOrderStatus::InProgress);
                $this->syncTimeLogOnTransition($workOrder, WorkOrderStatus::InProgress, $createdBy);
            }

            return $workOrder;
        });

        event(new WorkOrderCreated($workOrder));

        return $workOrder;
    }

    /**
     * Convert an approved MaintenanceRequest into a WorkOrder (draft).
     * Updates MR status to Converted.
     */
    public function createFromMaintenanceRequest(
        MaintenanceRequest $mr,
        array $data,
        User $createdBy,
    ): WorkOrder {
        return DB::transaction(function () use ($mr, $data, $createdBy): WorkOrder {
            $workOrder = $this->create([
                ...$data,
                'tenant_id' => $mr->tenant_id,
                'maintenance_request_id' => $mr->id,
                'equipment_id' => $mr->equipment_id,
                'work_order_type' => isset($data['work_order_type'])
                    ? ($data['work_order_type'] instanceof WorkOrderType ? $data['work_order_type']->value : $data['work_order_type'])
                    : $mr->request_type->value,
                'priority' => $data['priority'] ?? $mr->priority->value,
                'title' => $data['title'] ?? $mr->title,
                'description' => $data['description'] ?? $mr->description,
            ], $createdBy);

            $mr->update([
                'status' => MaintenanceRequestStatus::Converted->value,
                'work_order_id' => $workOrder->id,
            ]);

            // Auto-assign preliminary technician if one was set on the request
            if ($mr->preliminary_technician_id) {
                $technician = User::find($mr->preliminary_technician_id);
                if ($technician) {
                    $this->assignTechnician($workOrder, $technician, TechnicianRole::Technician);
                }
            }

            return $workOrder;
        });
    }

    // ── Transitions ───────────────────────────────────────────────────────────

    public function transition(
        WorkOrder $workOrder,
        WorkOrderStatus $toStatus,
        User $actor,
        array $extra = [],
        ?array $gps = null,
    ): WorkOrder {
        if (! $workOrder->status->canTransitionTo($toStatus)) {
            throw new \RuntimeException(
                "Cannot transition from [{$workOrder->status->value}] to [{$toStatus->value}]."
            );
        }

        if ($toStatus === WorkOrderStatus::Planned && $workOrder->technicians()->doesntExist()) {
            throw new \RuntimeException(
                'No es posible planificar la Orden de Trabajo porque no tiene técnicos asignados.'
            );
        }

        // A preventive closed with unanswered measurements is a preventive that
        // was never really done. Block it here, at the only door out.
        if ($toStatus === WorkOrderStatus::Completed) {
            $this->taskService->assertReadyToComplete($workOrder);
        }

        $fromStatus = $workOrder->status;

        $workOrder = DB::transaction(function () use ($workOrder, $fromStatus, $toStatus, $actor, $extra): WorkOrder {
            $timestamps = $this->transitionTimestamps($toStatus, $actor);

            $workOrder->update(array_merge(['status' => $toStatus->value], $timestamps, $extra));

            $this->syncEquipmentStatus($workOrder, $toStatus);
            $this->syncDowntimeEvent($workOrder, $toStatus);
            $this->syncInventoryOnTransition($workOrder, $fromStatus, $toStatus, $actor);
            $this->syncTimeLogOnTransition($workOrder, $toStatus, $actor);

            return $workOrder->refresh();
        });

        $this->notifyTechniciansOfStatusChange($workOrder, $toStatus);

        if (in_array($toStatus, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], strict: true)) {
            event(new WorkOrderStatusChanged($workOrder, $toStatus));
        }

        if ($gps !== null) {
            $this->locationService->record($workOrder->tenant_id, $actor, ActivityType::StatusChange, $workOrder->id, $gps);
        }

        return $workOrder;
    }

    public function changePriority(WorkOrder $workOrder, WorkOrderPriority $priority): WorkOrder
    {
        $workOrder->update(['priority' => $priority->value]);

        return $workOrder->refresh();
    }

    private function notifyTechniciansOfStatusChange(WorkOrder $workOrder, WorkOrderStatus $toStatus): void
    {
        $notifiableStatuses = [WorkOrderStatus::Planned, WorkOrderStatus::InProgress, WorkOrderStatus::Completed];

        if (! in_array($toStatus, $notifiableStatuses, strict: true)) {
            return;
        }

        $workOrder->technicians()
            ->withoutGlobalScopes()
            ->with('user')
            ->get()
            ->each(function ($technician) use ($workOrder, $toStatus): void {
                $technician->user?->notify(
                    new WorkOrderStatusChangedNotification($workOrder, $toStatus)
                );
            });
    }

    // ── Technicians ───────────────────────────────────────────────────────────

    public function assignTechnician(
        WorkOrder $workOrder,
        User $user,
        TechnicianRole|string $role,
        ?float $plannedHours = null,
        ?float $hourlyRate = null,
    ): WorkOrderTechnician {
        $role = $role instanceof TechnicianRole ? $role->value : $role;
        $technician = $workOrder->technicians()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $workOrder->tenant_id,
                'role' => $role,
                'planned_hours' => $plannedHours,
                'hourly_rate' => $hourlyRate,
            ]
        );

        if ($technician->wasRecentlyCreated) {
            $user->notify(new WorkOrderAssignedNotification($workOrder));
        }

        return $technician;
    }

    // ── Time Logs ─────────────────────────────────────────────────────────────

    public function logTime(
        WorkOrder $workOrder,
        User $user,
        CarbonInterface $startedAt,
        ?CarbonInterface $endedAt = null,
        ?string $description = null,
        ?array $gps = null,
    ): WorkOrderTimeLog {
        $hours = null;

        if ($endedAt !== null) {
            $hours = round($startedAt->diffInMinutes($endedAt) / 60, 2);
        }

        $log = $workOrder->timeLogs()->create([
            'tenant_id' => $workOrder->tenant_id,
            'user_id' => $user->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'hours' => $hours,
            'description' => $description,
        ]);

        $this->recalculateActualHours($workOrder);

        if ($gps !== null) {
            $this->locationService->record($workOrder->tenant_id, $user, ActivityType::TimeLog, $log->id, $gps);
        }

        return $log;
    }

    // ── Costs ─────────────────────────────────────────────────────────────────

    public function recalculateActualHours(WorkOrder $workOrder): void
    {
        $totalHours = $workOrder->timeLogs()->get()
            ->sum(fn (WorkOrderTimeLog $log): float => $log->computedHours());

        $workOrder->update(['actual_labor_hours' => $totalHours > 0 ? $totalHours : null]);
    }

    public function recalculateCosts(WorkOrder $workOrder): void
    {
        $laborCost = 0.0;

        foreach ($workOrder->technicians()->whereNotNull('hourly_rate')->get() as $tech) {
            $laborCost += $tech->actualHours() * (float) $tech->hourly_rate;
        }

        $partsCost = (float) $workOrder->parts()->whereNotNull('total_cost')->sum('total_cost');
        $externalCost = (float) ($workOrder->actual_cost_external ?? 0);
        $total = $laborCost + $partsCost + $externalCost;

        $workOrder->update([
            'actual_cost_labor' => $laborCost > 0 ? $laborCost : null,
            'actual_cost_parts' => $partsCost > 0 ? $partsCost : null,
            'actual_cost_total' => $total > 0 ? $total : null,
        ]);
    }

    /**
     * Manual override for when the automatic labor/parts calculation is
     * incomplete (e.g. a técnico's hourly_rate was never set) — lets an
     * admin/supervisor type the real costs in directly.
     *
     * @param  array{estimated_cost?: ?float, actual_cost_labor?: ?float, actual_cost_parts?: ?float, actual_cost_external?: ?float}  $data
     */
    public function updateCosts(WorkOrder $workOrder, array $data): void
    {
        $laborCost = $data['actual_cost_labor'] ?? null;
        $partsCost = $data['actual_cost_parts'] ?? null;
        $externalCost = $data['actual_cost_external'] ?? null;
        $total = ($laborCost ?? 0) + ($partsCost ?? 0) + ($externalCost ?? 0);

        $workOrder->update([
            'estimated_cost' => $data['estimated_cost'] ?? null,
            'actual_cost_labor' => $laborCost,
            'actual_cost_parts' => $partsCost,
            'actual_cost_external' => $externalCost,
            'actual_cost_total' => $total > 0 ? $total : null,
        ]);
    }

    // ── Signatures ────────────────────────────────────────────────────────────

    public function addSignature(
        WorkOrder $workOrder,
        User $user,
        WorkOrderSignatureType $type,
        ?string $notes = null,
        ?array $gps = null,
        UploadedFile|string|null $image = null,
    ): WorkOrderSignature {
        $attributes = [
            'tenant_id' => $workOrder->tenant_id,
            'user_id' => $user->id,
            'signed_at' => now(),
            'notes' => $notes,
        ];

        if ($image instanceof UploadedFile) {
            $attributes['image_path'] = Storage::disk(private_files_disk())->putFile(
                "work-orders/{$workOrder->id}/signatures",
                $image
            );
        } elseif (is_string($image) && $image !== '') {
            $path = $this->storeSignatureDataUrl($workOrder, $image);

            if ($path !== null) {
                $attributes['image_path'] = $path;
            }
        }

        $signature = $workOrder->signatures()->updateOrCreate(
            ['signature_type' => $type->value],
            $attributes
        );

        if ($gps !== null) {
            $this->locationService->record($workOrder->tenant_id, $user, ActivityType::Signature, $signature->id, $gps);
        }

        return $signature;
    }

    /**
     * Decode a data URL (e.g. `data:image/png;base64,...`) produced by the
     * on-screen signature pad and store it as the signature's image.
     */
    private function storeSignatureDataUrl(WorkOrder $workOrder, string $dataUrl): ?string
    {
        if (! preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], strict: true);

        if ($binary === false) {
            return null;
        }

        $path = "work-orders/{$workOrder->id}/signatures/".Str::uuid()->toString().'.'.$matches[1];

        Storage::disk(private_files_disk())->put($path, $binary);

        return $path;
    }

    // ── Equipment Status Sync ─────────────────────────────────────────────────

    /**
     * Auto-transition equipment.status based on WO lifecycle.
     * Only acts when equipment_stopped=true on the WO.
     *
     * The equipment returns to service when the work is *completed* (or the WO
     * is cancelled), not when it is administratively closed — the machine is
     * physically running again the moment the técnico finishes, so the
     * availability clock must stop there and not wait for supervisor sign-off.
     */
    private function syncEquipmentStatus(WorkOrder $workOrder, WorkOrderStatus $toStatus): void
    {
        if (! $workOrder->equipment_stopped) {
            return;
        }

        $equipment = $workOrder->equipment;

        if ($toStatus === WorkOrderStatus::InProgress) {
            $equipment->update(['status' => EquipmentStatus::UnderMaintenance->value]);

            return;
        }

        if ($toStatus === WorkOrderStatus::Completed || $toStatus === WorkOrderStatus::Cancelled) {
            // Completed/Verified/Closed WOs no longer hold the equipment down —
            // their work is finished. Only WOs whose work is still pending
            // (draft/planned/in-progress/on-hold) keep it under maintenance.
            $hasOtherActiveWOs = WorkOrder::withoutGlobalScopes()
                ->where('equipment_id', $equipment->id)
                ->where('id', '!=', $workOrder->id)
                ->where('equipment_stopped', true)
                ->whereNotIn('status', [
                    WorkOrderStatus::Completed->value,
                    WorkOrderStatus::Verified->value,
                    WorkOrderStatus::Closed->value,
                    WorkOrderStatus::Cancelled->value,
                ])
                ->exists();

            if (! $hasOtherActiveWOs) {
                $equipment->update(['status' => EquipmentStatus::Active->value]);
            }
        }
    }

    // ── Downtime Event Sync ───────────────────────────────────────────────────

    /**
     * Create or close the downtime/failure event tied to this WO.
     *
     * Fires when the equipment was stopped (any WO type — a planned preventive
     * stop is downtime too) OR when the WO type is itself a failure (corrective/
     * emergency), so reliability KPIs count the failure even when the machine
     * kept running. A failure with no stoppage is recorded as a point-in-time
     * event (closed immediately, zero duration): it feeds failure_count / MTBF /
     * Pareto but never touches availability or the equipment status.
     *
     * Real stoppages are closed when the work is *completed* (using actual_end_at,
     * the real end of execution) rather than at administrative closure, so MTTR
     * and availability are not inflated by a delayed supervisor sign-off. A
     * supervisor rejection (Completed → InProgress) re-opens the paro.
     */
    private function syncDowntimeEvent(WorkOrder $workOrder, WorkOrderStatus $toStatus): void
    {
        if (! $workOrder->equipment_stopped && ! $workOrder->work_order_type->registersFailure()) {
            return;
        }

        $causeType = EquipmentDowntimeCauseType::fromWorkOrderType($workOrder->work_order_type);

        if ($toStatus === WorkOrderStatus::InProgress) {
            $startedAt = $workOrder->actual_start_at ?? now();
            $stopped = (bool) $workOrder->equipment_stopped;

            $event = EquipmentDowntimeEvent::firstOrCreate(
                ['work_order_id' => $workOrder->id],
                [
                    'tenant_id' => $workOrder->tenant_id,
                    'plant_id' => $workOrder->plant_id ?? $workOrder->equipment?->plant_id,
                    'equipment_id' => $workOrder->equipment_id,
                    'work_order_number' => $workOrder->work_order_number,
                    'started_at' => $startedAt,
                    'cause_type' => $causeType->value,
                    'stoppage_category' => StoppageCategory::fromWorkOrderType($workOrder->work_order_type)->value,
                    'was_planned' => $causeType->wasPlanned(),
                    'source' => 'work_order',
                    // A failure with the line still running costs no production
                    // hours — it must not be subtracted from plant efficiency.
                    'affects_production' => $stopped,
                    // No stoppage → point-in-time failure: close it now with zero
                    // downtime so it counts as a failure without hurting availability.
                    'ended_at' => $stopped ? null : $startedAt,
                    'duration_minutes' => $stopped ? null : 0,
                ]
            );

            // Rejected verification re-opened a real paro: clear the close so it
            // keeps accruing. Only meaningful for stoppages (point-in-time
            // failures stay closed).
            if ($stopped && ! $event->wasRecentlyCreated && $event->ended_at !== null) {
                $event->update(['ended_at' => null, 'duration_minutes' => null]);
            }

            $workOrder->equipment->update(['last_failure_at' => $startedAt]);

            return;
        }

        if (in_array($toStatus, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled, WorkOrderStatus::Closed], strict: true)) {
            $event = EquipmentDowntimeEvent::where('work_order_id', $workOrder->id)->first();

            if ($event === null) {
                return;
            }

            $updates = [];

            // Propagate the failure mode diagnosed at completion — works for both
            // real paros (still open) and point-in-time failures (already closed).
            if ($workOrder->failure_mode !== null && $event->failure_mode === null) {
                $updates['failure_mode'] = $workOrder->failure_mode instanceof FailureMode
                    ? $workOrder->failure_mode->value
                    : $workOrder->failure_mode;
            }

            // Close a still-open paro at the real end of execution. A WO already
            // closed at Completed must not have its real end overwritten later.
            if ($event->ended_at === null) {
                $endedAt = $workOrder->actual_end_at ?? now();
                $updates['ended_at'] = $endedAt;
                $updates['duration_minutes'] = $workOrder->downtime_minutes
                    ?? (int) abs($event->started_at->diffInMinutes($endedAt));
            }

            if ($updates !== []) {
                $event->update($updates);
            }
        }
    }

    /**
     * Opens/closes a time log entry for the acting técnico as the OT moves
     * through Iniciar/Pausar/Reanudar/Completar, so "horas reales" fills in
     * automatically instead of requiring a separate manual time entry.
     */
    private function syncTimeLogOnTransition(WorkOrder $workOrder, WorkOrderStatus $toStatus, User $actor): void
    {
        if ($toStatus === WorkOrderStatus::InProgress) {
            $hasOpenLog = $workOrder->timeLogs()
                ->where('user_id', $actor->id)
                ->whereNull('ended_at')
                ->exists();

            if (! $hasOpenLog) {
                $workOrder->timeLogs()->create([
                    'tenant_id' => $workOrder->tenant_id,
                    'user_id' => $actor->id,
                    'started_at' => now(),
                ]);
            }

            return;
        }

        if (in_array($toStatus, [WorkOrderStatus::OnHold, WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], strict: true)) {
            $openLog = $workOrder->timeLogs()
                ->where('user_id', $actor->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first();

            if ($openLog !== null) {
                $endedAt = now();

                $openLog->update([
                    'ended_at' => $endedAt,
                    'hours' => round($openLog->started_at->diffInMinutes($endedAt) / 60, 2),
                ]);
            }

            $this->recalculateActualHours($workOrder);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function transitionTimestamps(WorkOrderStatus $toStatus, User $actor): array
    {
        return match ($toStatus) {
            WorkOrderStatus::InProgress => [
                'started_at' => now(),
                'actual_start_at' => now(),
            ],
            WorkOrderStatus::Completed => [
                'completed_at' => now(),
                'actual_end_at' => now(),
                'completed_by' => $actor->id,
            ],
            WorkOrderStatus::Verified => [
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ],
            WorkOrderStatus::Closed => [
                'closed_at' => now(),
            ],
            default => [],
        };
    }

    // ── Inventory Sync ────────────────────────────────────────────────────────

    private function syncInventoryOnTransition(
        WorkOrder $workOrder,
        WorkOrderStatus $fromStatus,
        WorkOrderStatus $toStatus,
        User $actor,
    ): void {
        match (true) {
            $toStatus === WorkOrderStatus::Planned => $this->workOrderInventoryService->reservePartsForWorkOrder($workOrder, $actor),

            $toStatus === WorkOrderStatus::Completed => $this->workOrderInventoryService->consumePartsForWorkOrder($workOrder, $actor),

            $toStatus === WorkOrderStatus::Cancelled => $this->workOrderInventoryService->releasePartsForWorkOrder($workOrder, $actor),

            // Supervisor rejection: Completed → InProgress — undo consumption and re-reserve
            $toStatus === WorkOrderStatus::InProgress && $fromStatus === WorkOrderStatus::Completed => $this->workOrderInventoryService->undoConsumptionForWorkOrder($workOrder, $actor),

            default => null,
        };
    }
}
