<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Enums\PlanVersion;
use App\Models\AnnualPlan;
use App\Models\Customer;
use App\Models\FruitSupplier;
use App\Models\KernelInventoryCount;
use App\Models\KernelTollLoad;
use App\Models\SalesDispatch;
use App\Models\SalesOffer;
use Carbon\CarbonImmutable;

/**
 * El informe mensual de gerencia del módulo comercial: lo que la Directora Administrativa
 * lleva al comité cada mes, en PDF y en Excel. Todo sale de los mismos cálculos que el
 * cuadro cruce, así que el informe y la pantalla nunca dicen cosas distintas.
 *
 * Cada cifra va dos veces: la del mes y la acumulada del año hasta ese mes, contra el plan
 * vigente (la reproyección, o el original si todavía no se aprobó).
 */
class MonthlyManagementReport
{
    public const MONTHS = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    public function __construct(
        private CommercialPlanReport $report,
        private AnnualPlanService $plans,
        private OilTankService $tanks,
    ) {}

    /** El mes más reciente con despachos, para proponerlo por defecto. */
    public function latestMonth(string $tenantId): CarbonImmutable
    {
        $last = SalesDispatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->max('loaded_on');

        return CarbonImmutable::parse($last ?? now())->startOfMonth();
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $tenantId, int $year, int $month): array
    {
        $plan = $this->plans->current($tenantId, $year);
        $customers = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('name', 'id')->all();
        $suppliers = FruitSupplier::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
        $upTo = fn (array $months): float => round(array_sum(array_intersect_key($months, array_flip(range(1, $month)))), 2);
        $in = fn (array $months): float => round((float) ($months[$month] ?? 0), 2);
        $ratio = fn (float $done, float $planned): ?float => $planned > 0 ? $done / $planned : null;

        // Aceite y palmiste por cliente, sin «por concretar» en el cumplimiento (como el Excel).
        $products = [];

        foreach (CommercialProduct::cases() as $product) {
            $projected = $this->report->salesPlan($plan, $product);
            $dispatched = $this->report->dispatched($tenantId, $year, $product);
            unset($projected[AnnualPlanService::UNASSIGNED]);
            $rows = [];

            foreach (array_unique([...array_keys($projected), ...array_keys($dispatched)]) as $id) {
                $p = $projected[$id] ?? [];
                $d = $dispatched[$id] ?? [];
                $rows[$id] = [
                    'name' => $customers[$id] ?? '—',
                    'month_projected' => $in($p),
                    'month_dispatched' => $in($d),
                    'month_ratio' => $ratio($in($d), $in($p)),
                    'ytd_projected' => $upTo($p),
                    'ytd_dispatched' => $upTo($d),
                    'ytd_ratio' => $ratio($upTo($d), $upTo($p)),
                ];
            }

            uasort($rows, fn (array $a, array $b): int => $b['ytd_projected'] <=> $a['ytd_projected']);
            $projectedMonths = $this->report->monthTotals($projected);
            $dispatchedMonths = $this->report->monthTotals($dispatched);

            $products[$product->value] = [
                'rows' => array_filter($rows, fn (array $r): bool => $r['ytd_projected'] > 0 || $r['ytd_dispatched'] > 0),
                'projected_months' => $projectedMonths,
                'dispatched_months' => $dispatchedMonths,
                'month_projected' => $in($projectedMonths),
                'month_dispatched' => $in($dispatchedMonths),
                'ytd_projected' => $upTo($projectedMonths),
                'ytd_dispatched' => $upTo($dispatchedMonths),
            ];
        }

        // Facturación del mes por cliente, por la fecha de la factura.
        $invoicing = [];

        foreach (CommercialProduct::cases() as $product) {
            foreach ($this->report->invoicedByMonth($tenantId, $year, $product) as $id => $months) {
                $row = $months[$month] ?? null;
                $ytdAmount = array_sum(array_column(array_intersect_key($months, array_flip(range(1, $month))), 'amount'));

                if ($row === null && $ytdAmount <= 0) {
                    continue;
                }

                $invoicing[] = [
                    'name' => $customers[$id] ?? '—',
                    'product' => $product->shortLabel(),
                    'tons' => $row['tons'] ?? 0.0,
                    'amount' => $row['amount'] ?? 0.0,
                    'price' => ($row['tons'] ?? 0) > 0 ? $row['amount'] / $row['tons'] : null,
                    'ytd_amount' => round($ytdAmount, 2),
                ];
            }
        }

        usort($invoicing, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        // Fruta por proveedor.
        $fruitPlan = $this->report->fruitPlan($plan);
        $fruitReal = $this->report->fruitReal($tenantId, $year);
        $fruit = [];

        foreach ($suppliers as $id => $name) {
            $p = $fruitPlan[$id] ?? [];
            $r = $fruitReal[$id] ?? [];

            if ($upTo($p) <= 0 && $upTo($r) <= 0) {
                continue;
            }

            $fruit[] = [
                'name' => $name,
                'month_projected' => $in($p),
                'month_received' => $in($r),
                'month_ratio' => $ratio($in($r), $in($p)),
                'ytd_projected' => $upTo($p),
                'ytd_received' => $upTo($r),
                'ytd_ratio' => $ratio($upTo($r), $upTo($p)),
            ];
        }

        $fruitPlanMonths = $this->report->monthTotals($fruitPlan);
        $fruitRealMonths = $this->report->monthTotals($fruitReal);

        // Planta: aceite esperado, producido según los tanques, maquila del mes e inventario.
        $plantMonths = $this->report->plantMonths($plan, $fruitRealMonths);
        $produced = $this->tanks->monthlyProduction($tenantId, $year);
        $start = CarbonImmutable::create($year, $month, 1);
        $tolls = KernelTollLoad::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereBetween('loaded_on', [$start->toDateString(), $start->endOfMonth()->toDateString()])->get();
        $inventory = KernelInventoryCount::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->whereDate('counted_on', '<=', $start->endOfMonth()->toDateString())->orderByDesc('counted_on')->first();

        // Cartera de hoy y ofertas abiertas.
        $aging = $this->report->aging($tenantId);
        $offers = SalesOffer::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ->open()->withExecution()->with(['customer' => fn ($q) => $q->withoutGlobalScopes()])->get()
            ->map(fn (SalesOffer $o): array => [
                'number' => $o->number,
                'customer' => $o->customer?->name,
                'product' => $o->product->shortLabel(),
                'contracted' => (float) $o->contracted_tons,
                'delivered' => $o->deliveredTons(),
                'remaining' => $o->remainingTons(),
            ])
            ->sortByDesc('remaining')->values()->all();

        $invoicedMonth = round(array_sum(array_column($invoicing, 'amount')), 2);
        $invoicedTonsMonth = round(array_sum(array_map(fn (array $r): float => $r['product'] === 'CPO' ? $r['tons'] : 0, $invoicing)), 2);
        $invoicedCpoMonth = round(array_sum(array_map(fn (array $r): float => $r['product'] === 'CPO' ? $r['amount'] : 0, $invoicing)), 2);
        $cpo = $products['cpo'];

        return [
            'year' => $year,
            'month' => $month,
            'monthName' => self::MONTHS[$month],
            'periodLabel' => self::MONTHS[$month].' de '.$year,
            // Solo el original se aprueba: la reproyección es, por definición, la vigente.
            'planLabel' => match (true) {
                $plan === null => 'sin plan para el año',
                $plan->version === PlanVersion::Original && $plan->approved_at === null => 'plan original (sin aprobar)',
                $plan->version === PlanVersion::Original => 'plan original',
                default => 'reproyección vigente',
            },
            'hasPlan' => $plan instanceof AnnualPlan,
            'summary' => [
                'oil_month_projected' => $cpo['month_projected'],
                'oil_month_dispatched' => $cpo['month_dispatched'],
                'oil_month_ratio' => $ratio($cpo['month_dispatched'], $cpo['month_projected']),
                'oil_ytd_projected' => $cpo['ytd_projected'],
                'oil_ytd_dispatched' => $cpo['ytd_dispatched'],
                'oil_ytd_ratio' => $ratio($cpo['ytd_dispatched'], $cpo['ytd_projected']),
                'fruit_month_projected' => $in($fruitPlanMonths),
                'fruit_month_received' => $in($fruitRealMonths),
                'fruit_month_ratio' => $ratio($in($fruitRealMonths), $in($fruitPlanMonths)),
                'fruit_ytd_projected' => $upTo($fruitPlanMonths),
                'fruit_ytd_received' => $upTo($fruitRealMonths),
                'fruit_ytd_ratio' => $ratio($upTo($fruitRealMonths), $upTo($fruitPlanMonths)),
                'pko_month_dispatched' => $products['pko']['month_dispatched'],
                'pko_ytd_dispatched' => $products['pko']['ytd_dispatched'],
                'invoiced_month' => $invoicedMonth,
                'invoiced_ytd' => round(array_sum(array_column($invoicing, 'ytd_amount')), 2),
                'oil_price_month' => $invoicedTonsMonth > 0 ? $invoicedCpoMonth / $invoicedTonsMonth : null,
                'receivable' => round(array_sum(array_column($aging, 'total')), 2),
                'overdue' => round(array_sum(array_column($aging, 'overdue')), 2),
            ],
            'products' => $products,
            'invoicing' => $invoicing,
            'fruit' => $fruit,
            'fruit_plan_months' => $fruitPlanMonths,
            'fruit_real_months' => $fruitRealMonths,
            'plant' => [
                'expected_cpo' => $plantMonths[$month]['cpo'],
                'tea' => $plantMonths[$month]['tea'],
                'real_cpo_estimate' => $plantMonths[$month]['real_cpo'],
                'produced' => $produced[$month] ?? 0.0,
                'toll_loads' => $tolls->count(),
                'toll_cake_tons' => round($tolls->sum('cake_kg') / 1000, 2),
                'toll_balance' => $tolls->contains(fn (KernelTollLoad $l): bool => $l->balance() !== null) ? round($tolls->sum(fn (KernelTollLoad $l): float => (float) $l->balance()), 2) : null,
                'inventory' => $inventory === null ? null : ['date' => $inventory->counted_on, 'pko' => (float) $inventory->pko_tons, 'cake' => (float) $inventory->cake_tons],
                'tanks' => $this->tanks->latest($tenantId),
            ],
            'aging' => array_map(fn (array $row, string $id): array => ['name' => $customers[$id] ?? '—', ...$row], $aging, array_keys($aging)),
            'offers' => $offers,
        ];
    }
}
