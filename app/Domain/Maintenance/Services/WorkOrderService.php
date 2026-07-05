<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Assets\Enums\EquipmentDowntimeCauseType;
use App\Domain\Assets\Enums\EquipmentStatus;
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

            // Emergency WOs start InProgress directly — trigger equipment sync
            if ($type->startsInProgress() && $workOrder->equipment_stopped) {
                $this->syncEquipmentStatus($workOrder, WorkOrderStatus::InProgress);
                $this->syncDowntimeEvent($workOrder, WorkOrderStatus::InProgress);
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

        $fromStatus = $workOrder->status;

        $workOrder = DB::transaction(function () use ($workOrder, $fromStatus, $toStatus, $actor, $extra): WorkOrder {
            $timestamps = $this->transitionTimestamps($toStatus, $actor);

            $workOrder->update(array_merge(['status' => $toStatus->value], $timestamps, $extra));

            $this->syncEquipmentStatus($workOrder, $toStatus);
            $this->syncDowntimeEvent($workOrder, $toStatus);
            $this->syncInventoryOnTransition($workOrder, $fromStatus, $toStatus, $actor);

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
        $totalHours = $workOrder->timeLogs()->whereNotNull('hours')->sum('hours');

        $workOrder->update(['actual_labor_hours' => $totalHours > 0 ? $totalHours : null]);
    }

    public function recalculateCosts(WorkOrder $workOrder): void
    {
        $laborCost = 0.0;

        foreach ($workOrder->technicians()->whereNotNull('hourly_rate')->get() as $tech) {
            $hours = $workOrder->timeLogs()
                ->where('user_id', $tech->user_id)
                ->whereNotNull('hours')
                ->sum('hours');

            $laborCost += (float) $hours * (float) $tech->hourly_rate;
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

        if ($toStatus === WorkOrderStatus::Closed || $toStatus === WorkOrderStatus::Cancelled) {
            $hasOtherActiveWOs = WorkOrder::withoutGlobalScopes()
                ->where('equipment_id', $equipment->id)
                ->where('id', '!=', $workOrder->id)
                ->where('equipment_stopped', true)
                ->whereNotIn('status', [
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
     * Create or close a downtime event tied to this WO.
     * Only acts when equipment_stopped=true on the WO.
     */
    private function syncDowntimeEvent(WorkOrder $workOrder, WorkOrderStatus $toStatus): void
    {
        if (! $workOrder->equipment_stopped) {
            return;
        }

        $causeType = EquipmentDowntimeCauseType::fromWorkOrderType($workOrder->work_order_type);

        if ($toStatus === WorkOrderStatus::InProgress) {
            $startedAt = $workOrder->actual_start_at ?? now();

            EquipmentDowntimeEvent::firstOrCreate(
                ['work_order_id' => $workOrder->id],
                [
                    'tenant_id' => $workOrder->tenant_id,
                    'equipment_id' => $workOrder->equipment_id,
                    'work_order_number' => $workOrder->work_order_number,
                    'started_at' => $startedAt,
                    'cause_type' => $causeType->value,
                    'was_planned' => $causeType->wasPlanned(),
                ]
            );

            $workOrder->equipment->update(['last_failure_at' => $startedAt]);

            return;
        }

        if ($toStatus === WorkOrderStatus::Closed || $toStatus === WorkOrderStatus::Cancelled) {
            $event = EquipmentDowntimeEvent::where('work_order_id', $workOrder->id)->first();

            if ($event === null) {
                return;
            }

            $endedAt = now();
            $durationMinutes = $workOrder->downtime_minutes
                ?? (int) abs($event->started_at->diffInMinutes($endedAt));

            $event->update([
                'ended_at' => $endedAt,
                'duration_minutes' => $durationMinutes,
            ]);
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
