<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Log;

/**
 * The missing engine.
 *
 * The plans, the schedules, the next_due calculations — all of it already existed
 * and none of it was ever called. A preventive program nobody triggers is a
 * spreadsheet with extra steps: the ~350 meter-driven routines of El Pajuil would
 * have silently never generated a single work order.
 *
 * This is the piece that turns the plan into work.
 */
class PreventiveWorkOrderGenerator
{
    public function __construct(
        private readonly WorkOrderService $workOrderService,
        private readonly MaintenancePlanService $planService,
        private readonly EquipmentMeterReadingService $meterService,
    ) {}

    /**
     * Generate the preventive work orders a tenant owes, looking `leadDays` ahead
     * so the planner receives them before they are late, not after.
     *
     * @return array{generated: int, skipped: int, work_orders: list<WorkOrder>}
     */
    public function generateForTenant(string $tenantId, User $actor, int $leadDays = 7): array
    {
        $plans = MaintenancePlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with(['schedule', 'equipment'])
            ->get();

        $generated = [];
        $skipped = 0;

        foreach ($plans as $plan) {
            try {
                if (! $this->shouldGenerate($plan, $leadDays)) {
                    $skipped++;

                    continue;
                }

                $generated[] = $this->generate($plan, $actor);
            } catch (\Throwable $e) {
                // One broken plan must not stop the other 349.
                $skipped++;

                Log::error('No se pudo generar la OT preventiva del plan.', [
                    'maintenance_plan_id' => $plan->id,
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'generated' => count($generated),
            'skipped' => $skipped,
            'work_orders' => $generated,
        ];
    }

    /**
     * A plan is due when its calendar date is within the lead window, or when the
     * equipment's real pace of use says its meter target lands inside it.
     */
    public function shouldGenerate(MaintenancePlan $plan, int $leadDays = 7): bool
    {
        $schedule = $plan->schedule;
        $equipment = $plan->equipment;

        if ($schedule === null || $equipment === null) {
            return false;
        }

        // A plan whose OT is still open does not need another one. This is what
        // makes the daily job safe to run twice.
        if ($this->hasOpenWorkOrder($plan)) {
            return false;
        }

        if ($plan->pause_when_equipment_inactive && ! $equipment->is_active) {
            return false;
        }

        if ($schedule->next_due_at !== null && $schedule->next_due_at->lte(now()->addDays($leadDays))) {
            return true;
        }

        if ($schedule->next_due_meter !== null) {
            $daysLeft = $this->meterService->daysUntilDue($equipment, $plan);

            // No measurable pace yet: fall back to the raw counter, so a machine
            // that is already past its target is not ignored forever.
            if ($daysLeft === null) {
                return $this->meterService->accumulatedReading($equipment) >= (float) $schedule->next_due_meter;
            }

            return $daysLeft <= $leadDays;
        }

        return false;
    }

    /**
     * Create the work order for a plan. Its tasks and checklist are copied and
     * frozen by WorkOrderService::create — the OT arrives ready to execute.
     */
    public function generate(MaintenancePlan $plan, User $actor): WorkOrder
    {
        $equipment = $plan->equipment;

        $workOrder = $this->workOrderService->create([
            'tenant_id' => $plan->tenant_id,
            'equipment_id' => $plan->equipment_id,
            'maintenance_plan_id' => $plan->id,
            'work_order_type' => WorkOrderType::Preventive->value,
            'priority' => $this->priorityFor($equipment)->value,
            'title' => $plan->name,
            'description' => $plan->description ?? "Preventivo generado automáticamente desde el plan {$plan->plan_number}.",
            'planned_start_at' => $plan->schedule?->next_due_at,
            'assigned_supervisor' => $plan->responsible_user_id,
        ], $actor);

        $plan->update(['last_generated_at' => now()]);

        return $workOrder;
    }

    /**
     * Close the loop: a completed preventive advances its plan's schedule. Without
     * this the plan would fall due once and stay due forever.
     */
    public function recordCompletion(WorkOrder $workOrder): void
    {
        $plan = $workOrder->maintenancePlan;

        if ($plan === null) {
            return;
        }

        // A supervisor rejection sends the OT back to InProgress, and completing it
        // again fires this event a second time. Without this guard the schedule
        // would advance twice for one execution — and the plan would silently skip
        // a cycle every time a técnico had to redo his work.
        if ($plan->schedule?->last_work_order_id === $workOrder->id) {
            return;
        }

        $this->planService->recordExecution(
            plan: $plan,
            workOrder: $workOrder,
            completedAt: $workOrder->actual_end_at ?? $workOrder->completed_at ?? now(),
            completedMeter: $workOrder->equipment !== null
                ? $this->meterService->accumulatedReading($workOrder->equipment)
                : null,
        );
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Only *unfinished* work blocks the next generation.
     *
     * A Completed or Verified OT is work that was already done — it is merely
     * waiting for an administrative signature. Treating it as "open" would mean a
     * supervisor who signs off late silently cancels the next preventive, which is
     * exactly how a maintenance program rots without anyone noticing.
     */
    private function hasOpenWorkOrder(MaintenancePlan $plan): bool
    {
        return WorkOrder::withoutGlobalScopes()
            ->where('maintenance_plan_id', $plan->id)
            ->whereIn('status', [
                WorkOrderStatus::Draft->value,
                WorkOrderStatus::Planned->value,
                WorkOrderStatus::InProgress->value,
                WorkOrderStatus::OnHold->value,
            ])
            ->exists();
    }

    /**
     * A preventive on a critical asset is not the same job as a preventive on a
     * spare pump. The criticality the plant already assigned decides the queue.
     */
    private function priorityFor(Equipment $equipment): WorkOrderPriority
    {
        return match ($equipment->criticality) {
            EquipmentCriticality::Critical => WorkOrderPriority::P2High,
            EquipmentCriticality::High => WorkOrderPriority::P3Medium,
            default => WorkOrderPriority::P5Planned,
        };
    }
}
