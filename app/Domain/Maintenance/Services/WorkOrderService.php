<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use App\Models\WorkOrderTimeLog;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    // ── Numbering ─────────────────────────────────────────────────────────────

    /**
     * Generate OT-{YEAR}-{EQUIPMENT_CODE}-{SEQUENTIAL}.
     * SEQUENTIAL is 6-digit, global per tenant per year.
     * Uses lockForUpdate() to prevent race conditions.
     */
    public function generateWorkOrderNumber(string $tenantId, string $equipmentCode): string
    {
        $year = date('Y');

        $last = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('work_order_number', 'like', "OT-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('work_order_number')
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
     */
    public function create(array $data, User $createdBy): WorkOrder
    {
        return DB::transaction(function () use ($data, $createdBy): WorkOrder {
            $type   = WorkOrderType::from($data['work_order_type']);
            $status = $type->startsInProgress()
                ? WorkOrderStatus::InProgress
                : WorkOrderStatus::Draft;

            $equipment = \App\Models\Equipment::withoutGlobalScopes()->findOrFail($data['equipment_id']);

            $number = $this->generateWorkOrderNumber($data['tenant_id'], $equipment->code);

            return WorkOrder::create([
                ...$data,
                'work_order_number' => $number,
                'status'            => $status->value,
                'plant_id'          => $data['plant_id'] ?? $equipment->plant_id,
                'area_id'           => $data['area_id'] ?? $equipment->area_id,
                'created_by'        => $createdBy->id,
                'started_at'        => $type->startsInProgress() ? now() : null,
                'actual_start_at'   => $type->startsInProgress() ? now() : null,
            ]);
        });
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
                'tenant_id'              => $mr->tenant_id,
                'maintenance_request_id' => $mr->id,
                'equipment_id'           => $mr->equipment_id,
                'work_order_type'        => $data['work_order_type'] ?? $mr->request_type->value,
                'priority'               => $data['priority'] ?? $mr->priority->value,
                'title'                  => $data['title'] ?? $mr->title,
                'description'            => $data['description'] ?? $mr->description,
            ], $createdBy);

            // Mark MR as converted
            $mr->update([
                'status'       => MaintenanceRequestStatus::Converted->value,
                'work_order_id' => $workOrder->id,
            ]);

            return $workOrder;
        });
    }

    // ── Transitions ───────────────────────────────────────────────────────────

    public function transition(
        WorkOrder $workOrder,
        WorkOrderStatus $toStatus,
        User $actor,
        array $extra = [],
    ): WorkOrder {
        if (! $workOrder->status->canTransitionTo($toStatus)) {
            throw new \RuntimeException(
                "Cannot transition from [{$workOrder->status->value}] to [{$toStatus->value}]."
            );
        }

        $timestamps = $this->transitionTimestamps($toStatus, $actor);

        $workOrder->update(array_merge(['status' => $toStatus->value], $timestamps, $extra));

        return $workOrder->refresh();
    }

    // ── Technicians ───────────────────────────────────────────────────────────

    public function assignTechnician(
        WorkOrder $workOrder,
        User $user,
        string $role,
        ?float $plannedHours = null,
        ?float $hourlyRate = null,
    ): \App\Models\WorkOrderTechnician {
        return $workOrder->technicians()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id'     => $workOrder->tenant_id,
                'role'          => $role,
                'planned_hours' => $plannedHours,
                'hourly_rate'   => $hourlyRate,
            ]
        );
    }

    // ── Time Logs ─────────────────────────────────────────────────────────────

    public function logTime(
        WorkOrder $workOrder,
        User $user,
        \Carbon\CarbonInterface $startedAt,
        ?\Carbon\CarbonInterface $endedAt = null,
        ?string $description = null,
    ): WorkOrderTimeLog {
        $hours = null;

        if ($endedAt !== null) {
            $hours = round($startedAt->diffInMinutes($endedAt) / 60, 2);
        }

        $log = $workOrder->timeLogs()->create([
            'tenant_id'  => $workOrder->tenant_id,
            'user_id'    => $user->id,
            'started_at' => $startedAt,
            'ended_at'   => $endedAt,
            'hours'      => $hours,
            'description' => $description,
        ]);

        $this->recalculateActualHours($workOrder);

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
        // Labor: sum of (hours * hourly_rate) per technician
        $laborCost = 0.0;

        foreach ($workOrder->technicians()->whereNotNull('hourly_rate')->get() as $tech) {
            $hours = $workOrder->timeLogs()
                ->where('user_id', $tech->user_id)
                ->whereNotNull('hours')
                ->sum('hours');

            $laborCost += (float) $hours * (float) $tech->hourly_rate;
        }

        // Parts: sum of total_cost
        $partsCost = (float) $workOrder->parts()->whereNotNull('total_cost')->sum('total_cost');

        $externalCost = (float) ($workOrder->actual_cost_external ?? 0);
        $total        = $laborCost + $partsCost + $externalCost;

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
    ): WorkOrderSignature {
        return $workOrder->signatures()->updateOrCreate(
            ['signature_type' => $type->value],
            [
                'tenant_id' => $workOrder->tenant_id,
                'user_id'   => $user->id,
                'signed_at' => now(),
                'notes'     => $notes,
            ]
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function transitionTimestamps(WorkOrderStatus $toStatus, User $actor): array
    {
        return match ($toStatus) {
            WorkOrderStatus::InProgress => [
                'started_at'          => now(),
                'actual_start_at'     => now(),
            ],
            WorkOrderStatus::Completed => [
                'completed_at'   => now(),
                'actual_end_at'  => now(),
                'completed_by'   => $actor->id,
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
}
