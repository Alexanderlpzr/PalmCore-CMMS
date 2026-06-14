<?php

namespace App\Domain\Automation\Evaluators;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Automation\Enums\AutomationMode;
use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Domain\Notifications\MaintenancePlanOverdueNotification;
use App\Domain\Notifications\ScheduleUpcomingNotification;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaintenancePlanRuleEvaluator
{
    public function __construct(
        private readonly MaintenanceRequestService $mrService,
        private readonly AlertService $alertService,
    ) {}

    // ── Overdue ───────────────────────────────────────────────────────────────

    public function evaluateOverdue(AutomationRule $rule): void
    {
        // R3 — whereNull('deleted_at') on plan handled via whereHas
        $schedules = MaintenanceSchedule::withoutGlobalScopes()
            ->where('tenant_id', $rule->tenant_id)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', now())
            ->whereHas('plan', fn ($q) => $q
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->whereNull('deleted_at')  // R3
            )
            ->with([
                'plan' => fn ($q) => $q->withoutGlobalScopes()->with('responsibleUser', 'equipment'),
            ])
            ->get();

        foreach ($schedules as $schedule) {
            $plan = $schedule->plan;
            if ($plan === null) {
                continue;
            }

            $this->processOverduePlan($rule, $schedule);
        }
    }

    private function processOverduePlan(AutomationRule $rule, MaintenanceSchedule $schedule): void
    {
        $plan = $schedule->plan;
        $action = $rule->mode === AutomationMode::Automatic ? 'created_mr' : 'notified_overdue';

        // Idempotency: only re-act if the plan was executed after our last action (new cycle)
        $existing = AutomationRuleExecution::where([
            'rule_id' => $rule->id,
            'entity_type' => 'maintenance_plan',
            'entity_id' => $plan->id,
            'action_taken' => $action,
        ])->first();

        if ($existing !== null) {
            $cycleReset = $schedule->last_completed_at;

            // Plan was resolved after we last acted → new overdue cycle, reset execution
            if ($cycleReset !== null && $existing->executed_at->lt($cycleReset)) {
                $existing->delete();
            } else {
                return; // Already acted in this cycle
            }
        }

        $this->alertService->create(new CreateAlertData(
            tenantId: $rule->tenant_id,
            severity: AlertSeverity::Warning,
            category: AlertCategory::Maintenance,
            title: "Plan vencido: {$plan->plan_number}",
            message: "Plan \"{$plan->name}\" superó su fecha de ejecución programada.",
            entityType: 'maintenance_plan',
            entityId: $plan->id,
            metadata: ['plan_number' => $plan->plan_number],
        ));

        if ($rule->mode === AutomationMode::NotifyOnly) {
            $user = $plan->responsibleUser;
            if ($user !== null) {
                $user->notify(new MaintenancePlanOverdueNotification($plan));
            }
            $this->record($rule, 'maintenance_plan', $plan->id, 'notified_overdue');

            return;
        }

        // Automatic: create MaintenanceRequest
        $actor = $plan->responsibleUser ?? $this->fallbackUser($rule->tenant_id);
        if ($actor === null) {
            return;
        }

        // Avoid creating a duplicate MR if one is already open for this equipment (preventive)
        $openMrExists = MaintenanceRequest::withoutGlobalScopes()
            ->where('equipment_id', $plan->equipment_id)
            ->where('request_type', MaintenanceRequestType::Preventive->value)
            ->whereNotIn('status', ['rejected', 'cancelled', 'converted'])
            ->exists();

        if ($openMrExists) {
            $this->record($rule, 'maintenance_plan', $plan->id, 'created_mr', ['skipped' => 'open_mr_exists']);

            return;
        }

        $mr = DB::transaction(fn () => $this->mrService->create([
            'tenant_id' => $rule->tenant_id,
            'equipment_id' => $plan->equipment_id,
            'request_type' => MaintenanceRequestType::Preventive->value,
            'priority' => MaintenanceRequestPriority::P2High->value,
            'title' => 'Mantenimiento vencido: '.$plan->name,
            'description' => 'Generado automáticamente por regla de automatización. Plan: '.$plan->plan_number,
        ], $actor));

        $this->record($rule, 'maintenance_plan', $plan->id, 'created_mr', ['mr_id' => $mr->id]);
    }

    // ── Upcoming ─────────────────────────────────────────────────────────────

    public function evaluateUpcoming(AutomationRule $rule): void
    {
        $daysAhead = (int) ($rule->configuration['days_ahead'] ?? 7);

        $schedules = MaintenanceSchedule::withoutGlobalScopes()
            ->where('tenant_id', $rule->tenant_id)
            ->whereNotNull('next_due_at')
            ->whereBetween('next_due_at', [now(), now()->addDays($daysAhead)])
            ->whereHas('plan', fn ($q) => $q
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->whereNull('deleted_at')
            )
            ->with(['plan' => fn ($q) => $q->withoutGlobalScopes()->with('responsibleUser')])
            ->get();

        foreach ($schedules as $schedule) {
            $plan = $schedule->plan;
            if ($plan === null) {
                continue;
            }

            $windowKey = now()->format('Y-W'); // weekly deduplication window

            // Idempotency: one notification per (plan, week)
            $alreadyNotified = AutomationRuleExecution::where([
                'rule_id' => $rule->id,
                'entity_type' => 'maintenance_plan',
                'entity_id' => $plan->id,
                'action_taken' => "notified_upcoming_{$windowKey}",
            ])->exists();

            if ($alreadyNotified) {
                continue;
            }

            $user = $plan->responsibleUser;
            if ($user !== null) {
                $user->notify(new ScheduleUpcomingNotification($plan, $daysAhead));
            }

            $this->record($rule, 'maintenance_plan', $plan->id, "notified_upcoming_{$windowKey}");
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function record(AutomationRule $rule, string $entityType, string $entityId, string $action, array $metadata = []): void
    {
        AutomationRuleExecution::firstOrCreate(
            [
                'rule_id' => $rule->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action_taken' => $action,
            ],
            [
                'metadata' => $metadata ?: null,
                'executed_at' => now(),
            ],
        );
    }

    private function fallbackUser(string $tenantId): ?User
    {
        return User::withoutGlobalScopes()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->where('is_active', true)
            ->orderBy('created_at')
            ->first();
    }
}
