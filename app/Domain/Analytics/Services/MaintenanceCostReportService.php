<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\MaintenanceBudget;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cuánto se gastó en mantenimiento en un mes, contra lo que había para gastar.
 *
 * El gasto se reconoce cuando el trabajo se completó (`completed_at`), no cuando
 * la OT se creó: una orden abierta en junio y cerrada en julio es gasto de julio,
 * que es como lo lee un control de presupuesto.
 *
 * Todo se arma sobre los tres componentes de costo de cada OT —mano de obra,
 * repuestos y terceros— de modo que el desglose siempre suma al total y el total
 * siempre suma a lo repartido por tipo. Sin esa disciplina, un reporte de
 * presupuesto donde los números no cuadran no sirve para presentarlo.
 */
class MaintenanceCostReportService
{
    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     period_label: string,
     *     work_order_count: int,
     *     total: float,
     *     labor: float,
     *     parts: float,
     *     external: float,
     *     by_type: array{corrective: float, preventive: float, predictive: float, other: float},
     *     budget: ?float,
     *     remaining: ?float,
     *     percent_used: ?float,
     *     is_over_budget: bool,
     * }
     */
    public function monthlyReport(string $tenantId, string $plantId, int $year, int $month): array
    {
        $workOrders = $this->completedWorkOrders($tenantId, $plantId, $year, $month);

        $labor = $this->sumComponent($workOrders, 'actual_cost_labor');
        $parts = $this->sumComponent($workOrders, 'actual_cost_parts');
        $external = $this->sumComponent($workOrders, 'actual_cost_external');
        $total = round($labor + $parts + $external, 2);

        $budget = MaintenanceBudget::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('plant_id', $plantId)
            ->where('year', $year)
            ->where('month', $month)
            ->value('amount');

        $budgetAmount = $budget !== null ? (float) $budget : null;
        $remaining = $budgetAmount !== null ? round($budgetAmount - $total, 2) : null;
        $percentUsed = $budgetAmount !== null && $budgetAmount > 0
            ? round($total / $budgetAmount * 100, 1)
            : null;

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => sprintf('%04d-%02d', $year, $month),
            'work_order_count' => $workOrders->count(),
            'total' => $total,
            'labor' => $labor,
            'parts' => $parts,
            'external' => $external,
            'by_type' => $this->byType($workOrders),
            'budget' => $budgetAmount,
            'remaining' => $remaining,
            'percent_used' => $percentUsed,
            'is_over_budget' => $budgetAmount !== null && $total > $budgetAmount,
        ];
    }

    /**
     * Las OTs del mes con su costo, para la lista y la exportación. Ordenadas de
     * más cara a más barata: la que más pesó en el presupuesto, arriba.
     *
     * @return Collection<int, WorkOrder>
     */
    public function completedWorkOrders(string $tenantId, string $plantId, int $year, int $month): Collection
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return WorkOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('plant_id', $plantId)
            ->whereNull('deleted_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->with(['equipment', 'area'])
            ->orderByDesc('actual_cost_total')
            ->get();
    }

    /**
     * @param  Collection<int, WorkOrder>  $workOrders
     */
    private function sumComponent(Collection $workOrders, string $column): float
    {
        return round((float) $workOrders->sum(fn (WorkOrder $wo): float => (float) ($wo->{$column} ?? 0)), 2);
    }

    /**
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return array{corrective: float, preventive: float, predictive: float, other: float}
     */
    private function byType(Collection $workOrders): array
    {
        $buckets = ['corrective' => 0.0, 'preventive' => 0.0, 'predictive' => 0.0, 'other' => 0.0];

        foreach ($workOrders as $wo) {
            // Una emergencia es un correctivo que no pudo esperar: cuenta en el
            // mismo balde para no partir el gasto reactivo en dos líneas.
            $bucket = match ($wo->work_order_type) {
                WorkOrderType::Corrective, WorkOrderType::Emergency => 'corrective',
                WorkOrderType::Preventive => 'preventive',
                WorkOrderType::Predictive => 'predictive',
                default => 'other',
            };

            $buckets[$bucket] += (float) ($wo->actual_cost_labor ?? 0)
                + (float) ($wo->actual_cost_parts ?? 0)
                + (float) ($wo->actual_cost_external ?? 0);
        }

        return array_map(fn (float $v): float => round($v, 2), $buckets);
    }
}
