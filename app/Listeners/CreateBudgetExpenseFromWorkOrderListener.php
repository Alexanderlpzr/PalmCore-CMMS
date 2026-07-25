<?php

namespace App\Listeners;

use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Events\WorkOrderStatusChanged;
use App\Models\MaintenanceBudgetExpense;

/**
 * Al cerrar una OT, su costo total se vuelca al presupuesto como un solo gasto de
 * mantenimiento. Así el «cuánto llevo gastado» del mes se alimenta solo con el
 * trabajo cerrado, sin desglosar ni recapturar cifras a mano.
 *
 * Escuchar el evento (en vez de crear el gasto dentro de WorkOrderService) mantiene
 * la dependencia en un solo sentido: el presupuesto conoce las OT, la OT no conoce
 * el presupuesto.
 */
class CreateBudgetExpenseFromWorkOrderListener
{
    public function handle(WorkOrderStatusChanged $event): void
    {
        if ($event->toStatus !== WorkOrderStatus::Closed) {
            return;
        }

        $workOrder = $event->workOrder;
        $amount = $workOrder->actual_cost_total;

        if ($amount === null || (float) $amount <= 0) {
            return;
        }

        MaintenanceBudgetExpense::create([
            'tenant_id' => $workOrder->tenant_id,
            'plant_id' => $workOrder->plant_id,
            'expense_date' => $workOrder->closed_at ?? now(),
            'amount' => (float) $amount,
            'category' => ExpenseCategory::Otros->value,
            'description' => "OT {$workOrder->work_order_number} — {$workOrder->title}",
            'created_by' => $workOrder->completed_by ?? $workOrder->created_by,
        ]);
    }
}
