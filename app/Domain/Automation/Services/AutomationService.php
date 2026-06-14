<?php

namespace App\Domain\Automation\Services;

use App\Domain\Automation\Enums\AutomationEventType;
use App\Domain\Automation\Evaluators\InventoryRuleEvaluator;
use App\Domain\Automation\Evaluators\MaintenancePlanRuleEvaluator;
use App\Domain\Automation\Evaluators\ReliabilityRuleEvaluator;
use App\Domain\Automation\Evaluators\WorkOrderRuleEvaluator;
use App\Events\AutomationRuleExecuted;
use App\Models\AutomationRule;

class AutomationService
{
    public function __construct(
        private readonly MaintenancePlanRuleEvaluator $maintenancePlanEvaluator,
        private readonly InventoryRuleEvaluator $inventoryEvaluator,
        private readonly WorkOrderRuleEvaluator $workOrderEvaluator,
        private readonly ReliabilityRuleEvaluator $reliabilityEvaluator,
    ) {}

    /**
     * Execute a single rule.
     *
     * Always reloads the rule fresh from DB to guard against stale mode/config
     * that was serialised in a queued job (R4: mode changed between dispatch and execution).
     */
    public function executeRule(AutomationRule $rule): void
    {
        // R4 — reload fresh to capture any mode/active changes made after dispatch
        $rule = AutomationRule::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->find($rule->id);

        if ($rule === null || ! $rule->isActionable()) {
            return;
        }

        match ($rule->event_type) {
            AutomationEventType::MaintenancePlanOverdue => $this->maintenancePlanEvaluator->evaluateOverdue($rule),
            AutomationEventType::ScheduleUpcoming => $this->maintenancePlanEvaluator->evaluateUpcoming($rule),
            AutomationEventType::StockLow => $this->inventoryEvaluator->evaluate($rule),
            AutomationEventType::WorkOrderOverdue => $this->workOrderEvaluator->evaluate($rule),
            AutomationEventType::MtbfBelowThreshold => $this->reliabilityEvaluator->evaluate($rule),
        };

        $rule->update(['last_executed_at' => now()]);

        event(new AutomationRuleExecuted($rule));
    }
}
