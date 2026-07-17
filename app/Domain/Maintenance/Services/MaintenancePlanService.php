<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MaintenancePlanService
{
    // ── Numbering ─────────────────────────────────────────────────────────────

    /**
     * Generate PM-{EQUIPMENT_CODE}-{FREQUENCY_LABEL}.
     * Appends -A, -B suffix if a plan with the same base number already exists for this tenant.
     */
    public function generatePlanNumber(
        string $tenantId,
        string $equipmentCode,
        MaintenanceTriggerSource $triggerSource,
        ?string $timeFrequency,
        ?int $meterInterval,
    ): string {
        $frequencyLabel = match ($triggerSource) {
            MaintenanceTriggerSource::Calendar => $timeFrequency
                ? MaintenanceTimeFrequency::from($timeFrequency)->shortLabel()
                : 'CALENDARIO',
            MaintenanceTriggerSource::Meter => ($meterInterval ?? '?').'H',
            MaintenanceTriggerSource::Hybrid => ($meterInterval ?? '?').'H',
            MaintenanceTriggerSource::Manual => 'MANUAL',
        };

        $base = "PM-{$equipmentCode}-{$frequencyLabel}";

        // Check for collision and add suffix if needed
        $existing = MaintenancePlan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('plan_number', 'like', $base.'%')
            ->pluck('plan_number');

        if ($existing->isEmpty()) {
            return $base;
        }

        if (! $existing->contains($base)) {
            return $base;
        }

        $suffix = 'A';
        while ($existing->contains("{$base}-{$suffix}")) {
            $suffix = chr(ord($suffix) + 1);
        }

        return "{$base}-{$suffix}";
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $data, User $createdBy): MaintenancePlan
    {
        return DB::transaction(function () use ($data): MaintenancePlan {
            $equipment = Equipment::withoutGlobalScopes()->findOrFail($data['equipment_id']);

            $triggerSource = $data['trigger_source'] instanceof MaintenanceTriggerSource
                ? $data['trigger_source']
                : MaintenanceTriggerSource::from($data['trigger_source']);

            $planNumber = $this->generatePlanNumber(
                $data['tenant_id'],
                $equipment->code,
                $triggerSource,
                $data['time_frequency'] ?? null,
                $data['meter_interval'] ?? null,
            );

            $plan = MaintenancePlan::create(array_merge($data, [
                'plan_number' => $planNumber,
                'is_active' => false,
            ]));

            // Create the 1:1 schedule immediately
            $this->initializeSchedule($plan);

            return $plan;
        });
    }

    // ── Schedule ──────────────────────────────────────────────────────────────

    /**
     * Create initial schedule for a new plan (all nulls — requires first activation).
     */
    public function initializeSchedule(MaintenancePlan $plan): MaintenanceSchedule
    {
        return MaintenanceSchedule::create([
            'tenant_id' => $plan->tenant_id,
            'maintenance_plan_id' => $plan->id,
            'times_executed' => 0,
            'times_skipped' => 0,
        ]);
    }

    /**
     * Activate a plan by setting the first next_due date/meter.
     */
    public function activate(
        MaintenancePlan $plan,
        ?CarbonInterface $firstDueAt = null,
        ?float $firstDueMeter = null,
    ): MaintenanceSchedule {
        $schedule = $plan->schedule ?? $this->initializeSchedule($plan);

        $schedule->update([
            'next_due_at' => $firstDueAt ?? $this->calculateInitialDueDate($plan),
            'next_due_meter' => $firstDueMeter ?? $this->calculateInitialDueMeter($plan),
        ]);

        $plan->update(['is_active' => true]);

        return $schedule->refresh();
    }

    /**
     * A meter-driven plan activated without a first target has no due point, and a
     * plan with no due point never generates anything — it would sit «active» and
     * silent forever. Default it to one full interval from where the equipment
     * stands today.
     */
    private function calculateInitialDueMeter(MaintenancePlan $plan): ?float
    {
        if (! $plan->trigger_source->requiresMeterInterval() || $plan->meter_interval === null) {
            return null;
        }

        $equipment = $plan->equipment
            ?? Equipment::withoutGlobalScopes()->find($plan->equipment_id);

        return (float) ($equipment?->accumulated_meter_reading ?? 0) + $plan->meter_interval;
    }

    /**
     * Record execution of a PM plan (called after WO is completed).
     * Updates the schedule and calculates next due date/meter.
     *
     * $workOrder is null for a manual execution (recordManualExecution) — the
     * schedule advances the same way either way, it just has no OT to point to.
     */
    public function recordExecution(
        MaintenancePlan $plan,
        ?WorkOrder $workOrder,
        CarbonInterface $completedAt,
        ?float $completedMeter = null,
        ?string $completedByUserId = null,
    ): MaintenanceSchedule {
        $schedule = $plan->schedule ?? $this->initializeSchedule($plan);

        [$nextDueAt, $nextDueMeter] = $this->calculateNextDue(
            $plan,
            $schedule,
            $completedAt,
            $completedMeter,
        );

        $schedule->update([
            'last_completed_at' => $completedAt,
            'last_completed_meter' => $completedMeter,
            'next_due_at' => $nextDueAt,
            'next_due_meter' => $nextDueMeter,
            'times_executed' => $schedule->times_executed + 1,
            'last_work_order_id' => $workOrder?->id,
        ]);

        $plan->update(['last_generated_at' => now()]);

        if ($plan->equipment_component_id !== null) {
            $this->logComponentIntervention(
                $plan,
                $workOrder,
                $completedAt,
                $completedMeter,
                $completedByUserId ?? $workOrder?->completed_by,
            );
        }

        return $schedule->refresh();
    }

    /**
     * Un técnico que hizo el cambio de aceite sin pasar por el ciclo completo de
     * una OT (técnico asignado, permisos, checklist respondido) igual necesita
     * dejar constancia: reinicia el horómetro del plan y suma una ejecución más,
     * sin fabricar una orden de trabajo falsa para conseguirlo.
     */
    public function recordManualExecution(
        MaintenancePlan $plan,
        User $actor,
        CarbonInterface $completedAt,
        ?float $completedMeter = null,
    ): MaintenanceSchedule {
        return $this->recordExecution(
            plan: $plan,
            workOrder: null,
            completedAt: $completedAt,
            completedMeter: $completedMeter,
            completedByUserId: $actor->id,
        );
    }

    /**
     * Deja constancia en la bitácora del componente sin que nadie tenga que
     * escribirla a mano: la OT que se acaba de completar YA es la prueba de que la
     * pieza tuvo su intervención. Sin esto, ComponentHistory solo tendría lo que un
     * técnico decidiera anotar por su cuenta — que en la práctica es nada.
     */
    private function logComponentIntervention(
        MaintenancePlan $plan,
        ?WorkOrder $workOrder,
        CarbonInterface $completedAt,
        ?float $completedMeter,
        ?string $completedByUserId,
    ): void {
        $description = $workOrder !== null
            ? "Generado automáticamente por el plan {$plan->plan_number} — {$plan->name} ({$workOrder->work_order_number})."
            : "Registrado manualmente por el plan {$plan->plan_number} — {$plan->name}.";

        $plan->equipmentComponent?->history()->create([
            'tenant_id' => $plan->tenant_id,
            'user_id' => $completedByUserId,
            'type' => 'maintenance',
            'description' => $description,
            'worked_hours_at_event' => $completedMeter,
            'occurred_at' => $completedAt,
        ]);
    }

    // ── Due Date Calculation ──────────────────────────────────────────────────

    /**
     * Calculate the initial due date for a new plan (no execution history).
     */
    private function calculateInitialDueDate(MaintenancePlan $plan): ?CarbonInterface
    {
        if (! $plan->trigger_source->requiresTimeFrequency()) {
            return null;
        }

        $frequency = $plan->time_frequency;

        if ($frequency === null) {
            return null;
        }

        return $frequency->addTo(now());
    }

    /**
     * Calculate next_due_at and next_due_meter after an execution.
     *
     * Time-based (calendar): fixed cadence — advance from theoretical due date to avoid drift.
     * Meter-based: floating cadence — add interval to the actual completed meter.
     * Hybrid: calendar uses fixed, meter uses floating.
     *
     * @return array{0: ?CarbonInterface, 1: ?float}
     */
    private function calculateNextDue(
        MaintenancePlan $plan,
        MaintenanceSchedule $schedule,
        CarbonInterface $completedAt,
        ?float $completedMeter,
    ): array {
        $nextDueAt = null;
        $nextDueMeter = null;

        // ── Time component (calendar or hybrid) ───────────────────────────────
        if ($plan->trigger_source->requiresTimeFrequency() && $plan->time_frequency !== null) {
            $frequency = $plan->time_frequency;

            if ($plan->cadence_mode === 'fixed' && $schedule->next_due_at !== null) {
                // Fixed: advance from theoretical due date (prevents drift)
                $nextDueAt = $frequency->addTo($schedule->next_due_at);

                // If still in the past, advance until it reaches the future, counting skipped cycles
                $skipped = 0;
                while ($nextDueAt->isPast()) {
                    $nextDueAt = $frequency->addTo($nextDueAt);
                    $skipped++;
                }

                if ($skipped > 0) {
                    $schedule->increment('times_skipped', $skipped);
                }
            } else {
                // Floating: calculate from actual completion date
                $nextDueAt = $frequency->addTo($completedAt);
            }
        }

        // ── Meter component (meter or hybrid) ─────────────────────────────────
        if ($plan->trigger_source->requiresMeterInterval() && $plan->meter_interval !== null) {
            $base = $completedMeter ?? 0.0;
            $nextDueMeter = $base + $plan->meter_interval;
        }

        return [$nextDueAt, $nextDueMeter];
    }

    // ── Status Queries ────────────────────────────────────────────────────────

    /**
     * Check whether a plan is overdue, considering grace periods.
     */
    public function isOverdue(MaintenancePlan $plan, ?float $currentMeter = null): bool
    {
        $schedule = $plan->schedule;

        if ($schedule === null) {
            return false;
        }

        // Time overdue (respecting grace period)
        if ($schedule->next_due_at !== null) {
            $graceDays = $plan->grace_period_days ?? 0;
            $overdueDate = $schedule->next_due_at->addDays($graceDays);

            if ($overdueDate->isPast()) {
                return true;
            }
        }

        // Meter overdue (respecting grace hours)
        if ($schedule->next_due_meter !== null && $currentMeter !== null) {
            $graceMeter = $plan->grace_meter_hours ?? 0;
            $overduePoint = $schedule->next_due_meter + $graceMeter;

            if ($currentMeter >= $overduePoint) {
                return true;
            }
        }

        return false;
    }
}
