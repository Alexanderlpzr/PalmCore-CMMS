<?php

namespace App\Domain\Automation\Evaluators;

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Automation\Enums\AutomationMode;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Notifications\WorkOrderOverdueNotification;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\WorkOrder;

class WorkOrderRuleEvaluator
{
    public function __construct(private readonly AlertService $alertService) {}

    public function evaluate(AutomationRule $rule): void
    {
        $overdueStatuses = [
            WorkOrderStatus::Draft->value,
            WorkOrderStatus::Planned->value,
            WorkOrderStatus::InProgress->value,
            WorkOrderStatus::OnHold->value,
        ];

        $workOrders = WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $rule->tenant_id)
            ->whereIn('status', $overdueStatuses)
            ->whereNotNull('planned_end_at')
            ->where('planned_end_at', '<', now())
            ->whereNull('deleted_at')  // R3
            ->with('assignedSupervisor')
            ->get();

        foreach ($workOrders as $workOrder) {
            $this->processOverdueWorkOrder($rule, $workOrder);
        }
    }

    private function processOverdueWorkOrder(AutomationRule $rule, WorkOrder $workOrder): void
    {
        $action = $rule->mode === AutomationMode::Automatic ? 'escalated' : 'notified_overdue';

        $existing = AutomationRuleExecution::where([
            'rule_id' => $rule->id,
            'entity_type' => 'work_order',
            'entity_id' => $workOrder->id,
            'action_taken' => $action,
        ])->first();

        if ($existing !== null) {
            return;
        }

        $severity = $rule->mode === AutomationMode::Automatic ? AlertSeverity::Critical : AlertSeverity::Warning;

        $this->alertService->create(new CreateAlertData(
            tenantId: $rule->tenant_id,
            severity: $severity,
            category: AlertCategory::WorkOrder,
            title: "OT vencida: {$workOrder->work_order_number}",
            message: 'Fecha planificada de cierre superada: '.($workOrder->planned_end_at?->format('d/m/Y H:i') ?? '—'),
            entityType: 'work_order',
            entityId: $workOrder->id,
            metadata: ['work_order_number' => $workOrder->work_order_number],
        ));

        if ($rule->mode === AutomationMode::NotifyOnly) {
            $this->notifySupervisor($workOrder);
            $this->record($rule, 'work_order', $workOrder->id, 'notified_overdue');

            return;
        }

        // Automatic: escalate priority to Critical + notify supervisor
        $previousPriority = $workOrder->priority->value;

        if ($workOrder->priority !== WorkOrderPriority::P1Critical) {
            $workOrder->update(['priority' => WorkOrderPriority::P1Critical]);
        }

        $this->notifySupervisor($workOrder);
        $this->record($rule, 'work_order', $workOrder->id, 'escalated', [
            'previous_priority' => $previousPriority,
        ]);
    }

    private function notifySupervisor(WorkOrder $workOrder): void
    {
        $supervisor = $workOrder->assignedSupervisor;
        if ($supervisor !== null) {
            $supervisor->notify(new WorkOrderOverdueNotification($workOrder));
        }
    }

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
}
