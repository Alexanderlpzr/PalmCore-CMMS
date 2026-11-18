<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\DispatchStatus;
use App\Models\AnnualPlan;
use App\Models\AnnualPlanFruit;
use App\Models\AnnualPlanMonth;
use App\Models\AnnualPlanSale;
use App\Models\FruitReception;
use App\Models\ProductionCalendarDay;
use App\Models\SalesDispatch;

/**
 * Las cuentas del plan y del cuadro cruce, en arreglos listos para pintar.
 *
 * Del plan: la fruta por proveedor y mes, el aceite (CPO = fruta × TEA), la almendra
 * (fruta × % almendra) y el PKO (almendra × % PKO) que sale de ella, las ventas planeadas
 * y si el aceite alcanza, mes a mes y acumulado desde el inventario inicial.
 *
 * De lo real: la fruta recibida por proveedor, la que procesó la planta según su
 * calendario, lo despachado a cada cliente (peso de llegada, por mes de cargue, como el
 * Excel), lo que va en tránsito y cómo va la facturación y la cartera.
 *
 * Los meses van de 1 a 12 en todas las listas.
 */
class CommercialPlanReport
{
    /**
     * @return array<int, array{fruit: float, capacity: ?float, utilization: ?float, tea: ?float, cpo: float, kernel: float, pko: float, sales_cpo: float, sales_pko: float, balance: float, cumulative: float, real_fruit: float, real_cpo: float}>
     */
    public function plantMonths(?AnnualPlan $plan, array $realFruitByMonth = []): array
    {
        $months = $plan ? AnnualPlanMonth::withoutGlobalScopes()->where('annual_plan_id', $plan->id)->get()->keyBy('month') : collect();
        $fruit = $this->monthTotals($this->fruitPlan($plan));
        $salesCpo = $this->monthTotals($this->salesPlan($plan, CommercialProduct::Cpo));
        $salesPko = $this->monthTotals($this->salesPlan($plan, CommercialProduct::Pko));
        $cumulative = (float) ($plan?->opening_oil_tons ?? 0);
        $rows = [];

        foreach (range(1, 12) as $month) {
            $parameters = $months->get($month);
            $tea = $parameters?->tea_percent !== null ? (float) $parameters->tea_percent : null;
            $kernelPercent = (float) ($parameters?->kernel_percent ?? AnnualPlanService::DEFAULT_KERNEL_PERCENT);
            $pkoPercent = (float) ($parameters?->pko_percent ?? AnnualPlanService::DEFAULT_PKO_PERCENT);
            $capacity = $parameters?->plant_capacity_tons !== null ? (float) $parameters->plant_capacity_tons : null;
            $tons = $fruit[$month];
            $cpo = round($tons * ($tea ?? 0) / 100, 2);
            $kernel = round($tons * $kernelPercent / 100, 2);
            $balance = round($cpo - $salesCpo[$month], 2);
            $cumulative = round($cumulative + $balance, 2);
            $realFruit = (float) ($realFruitByMonth[$month] ?? 0);

            $rows[$month] = [
                'fruit' => $tons,
                'capacity' => $capacity,
                'utilization' => $capacity ? $tons / $capacity : null,
                'tea' => $tea,
                'kernel_percent' => $kernelPercent,
                'pko_percent' => $pkoPercent,
                'cpo' => $cpo,
                'kernel' => $kernel,
                'pko' => round($kernel * $pkoPercent / 100, 2),
                'sales_cpo' => $salesCpo[$month],
                'sales_pko' => $salesPko[$month],
                'balance' => $balance,
                'cumulative' => $cumulative,
                'real_fruit' => $realFruit,
                'real_cpo' => round($realFruit * ($tea ?? 0) / 100, 2),
            ];
        }

        return $rows;
    }

    /** @return array<string, array<int, float>> proveedor → mes → tn */
    public function fruitPlan(?AnnualPlan $plan): array
    {
        if ($plan === null) {
            return [];
        }

        return $this->grid(AnnualPlanFruit::withoutGlobalScopes()
            ->where('annual_plan_id', $plan->id)
            ->get(['fruit_supplier_id as row', 'month', 'tons']));
    }

    /** @return array<string, array<int, float>> cliente (o «por-concretar») → mes → tn */
    public function salesPlan(?AnnualPlan $plan, CommercialProduct $product): array
    {
        if ($plan === null) {
            return [];
        }

        return $this->grid(AnnualPlanSale::withoutGlobalScopes()
            ->where('annual_plan_id', $plan->id)
            ->where('product', $product->value)
            ->get(['customer_id', 'month', 'tons'])
            ->map(fn (AnnualPlanSale $sale) => (object) ['row' => $sale->customer_id ?? AnnualPlanService::UNASSIGNED, 'month' => $sale->month, 'tons' => $sale->tons]));
    }

    /** @return array<string, array<int, float>> proveedor → mes → tn recibidas */
    public function fruitReal(string $tenantId, int $year): array
    {
        return $this->grid(FruitReception::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->whereNull('deleted_at')
            ->get(['fruit_supplier_id as row', 'month', 'tons']));
    }

    /** @return array<int, float> la fruta que la planta procesó cada mes, de su calendario */
    public function plantProcessed(string $tenantId, int $year): array
    {
        $totals = ProductionCalendarDay::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereYear('calendar_date', $year)
            ->selectRaw('extract(month from calendar_date)::int as month, coalesce(sum(processed_tons), 0) as tons')
            ->groupByRaw('extract(month from calendar_date)')
            ->pluck('tons', 'month');

        return array_map(fn (int $month): float => round((float) ($totals[$month] ?? 0), 2), array_combine(range(1, 12), range(1, 12)));
    }

    /** @return array<string, array<int, float>> cliente → mes de cargue → tn recibidas por el cliente */
    public function dispatched(string $tenantId, int $year, CommercialProduct $product): array
    {
        return $this->grid($this->dispatches($tenantId, $year, $product)
            ->whereNotNull('arrival_weight_tons')
            ->selectRaw('customer_id as row, extract(month from loaded_on)::int as month, sum(arrival_weight_tons) as tons')
            ->groupBy('customer_id')
            ->groupByRaw('extract(month from loaded_on)')
            ->get());
    }

    /** @return array<string, float> cliente → tn en camino (peso de salida de lo que no ha llegado) */
    public function inTransit(string $tenantId, int $year, CommercialProduct $product): array
    {
        return $this->dispatches($tenantId, $year, $product)
            ->where('status', DispatchStatus::Cargado->value)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, sum(departure_weight_tons) as tons, count(*) as loads')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->customer_id => ['tons' => round((float) $row->tons, 2), 'loads' => (int) $row->loads]])
            ->all();
    }

    /**
     * Facturación y cartera de cada cliente en el año: lo recibido con y sin factura, lo
     * facturado, lo pagado y lo que se debe.
     *
     * @return array<string, array{invoiced_tons: float, uninvoiced_tons: float, invoiced: float, paid: float, receivable: float, invoices_pending: int}>
     */
    public function billing(string $tenantId, int $year, CommercialProduct $product): array
    {
        $paid = DispatchStatus::Pagado->value;

        return $this->dispatches($tenantId, $year, $product)
            ->groupBy('customer_id')
            ->selectRaw('customer_id')
            ->selectRaw('coalesce(sum(case when invoice_number is not null then arrival_weight_tons end), 0) as invoiced_tons')
            ->selectRaw('coalesce(sum(case when invoice_number is null then arrival_weight_tons end), 0) as uninvoiced_tons')
            ->selectRaw('coalesce(sum(case when invoice_number is not null then invoice_amount end), 0) as invoiced')
            ->selectRaw("coalesce(sum(case when status = '{$paid}' then invoice_amount end), 0) as paid")
            ->selectRaw("count(case when invoice_number is not null and status <> '{$paid}' then 1 end) as invoices_pending")
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->customer_id => [
                'invoiced_tons' => round((float) $row->invoiced_tons, 2),
                'uninvoiced_tons' => round((float) $row->uninvoiced_tons, 2),
                'invoiced' => round((float) $row->invoiced, 2),
                'paid' => round((float) $row->paid, 2),
                'receivable' => round((float) $row->invoiced - (float) $row->paid, 2),
                'invoices_pending' => (int) $row->invoices_pending,
            ]])
            ->all();
    }

    /**
     * Lo facturado cada mes —por la fecha de la factura— y a cuánto salió la tonelada.
     *
     * @return array<string, array<int, array{amount: float, tons: float}>>
     */
    public function invoicedByMonth(string $tenantId, int $year, CommercialProduct $product): array
    {
        $rows = SalesDispatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('product', $product->value)
            ->whereNotNull('invoice_number')
            ->whereYear('invoiced_on', $year)
            ->groupBy('customer_id')
            ->groupByRaw('extract(month from invoiced_on)')
            ->selectRaw('customer_id, extract(month from invoiced_on)::int as month, sum(invoice_amount) as amount, sum(arrival_weight_tons) as tons')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[$row->customer_id][(int) $row->month] = ['amount' => round((float) $row->amount, 2), 'tons' => round((float) $row->tons, 2)];
        }

        return $result;
    }

    /** @return array<int, float> la suma de cada mes de una cuadrícula */
    public function monthTotals(array $grid): array
    {
        $totals = array_fill(1, 12, 0.0);

        foreach ($grid as $months) {
            foreach ($months as $month => $tons) {
                $totals[$month] = round($totals[$month] + $tons, 2);
            }
        }

        return $totals;
    }

    private function dispatches(string $tenantId, int $year, CommercialProduct $product)
    {
        return SalesDispatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('product', $product->value)
            ->whereYear('loaded_on', $year);
    }

    /**
     * @param  iterable<object{row: ?string, month: int|string, tons: mixed}>  $rows
     * @return array<string, array<int, float>>
     */
    private function grid(iterable $rows): array
    {
        $grid = [];

        foreach ($rows as $row) {
            $key = (string) $row->row;
            $grid[$key][(int) $row->month] = round(($grid[$key][(int) $row->month] ?? 0) + (float) $row->tons, 2);
        }

        return $grid;
    }
}
