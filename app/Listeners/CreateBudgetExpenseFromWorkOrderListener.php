<?php

namespace App\Listeners;

use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Events\WorkOrderStatusChanged;
use App\Models\MaintenanceBudgetExpense;
use App\Models\WorkOrder;

/**
 * Al cerrar una OT, su costo se vuelca al presupuesto como un gasto por cada
 * rubro con monto (mano de obra, repuestos, consumibles, terceros). Así el
 * «en qué se invirtió» del presupuesto queda desglosado por concepto en vez de
 * un solo número sin explicar, sin recapturar cifras a mano.
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

        $buckets = [
            [ExpenseCategory::ManoDeObra, $workOrder->actual_cost_labor],
            [ExpenseCategory::Repuestos, $workOrder->actual_cost_parts],
            [ExpenseCategory::Lubricantes, $workOrder->actual_cost_consumables],
            [ExpenseCategory::ServiciosTerceros, $workOrder->actual_cost_external],
        ];

        foreach ($buckets as [$category, $amount]) {
            if ($amount === null || (float) $amount <= 0) {
                continue;
            }

            $this->recordExpense($workOrder, $category, (float) $amount);
        }
    }

    private function recordExpense(WorkOrder $workOrder, ExpenseCategory $category, float $amount): void
    {
        MaintenanceBudgetExpense::create([
            'tenant_id' => $workOrder->tenant_id,
            'plant_id' => $workOrder->plant_id,
            'expense_date' => $workOrder->closed_at ?? now(),
            'amount' => $amount,
            'category' => $category->value,
            'description' => "OT {$workOrder->work_order_number} — {$workOrder->title}",
            'created_by' => $workOrder->completed_by ?? $workOrder->created_by,
        ]);
    }
}
