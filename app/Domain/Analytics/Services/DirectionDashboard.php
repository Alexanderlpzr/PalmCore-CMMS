<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Commercial\Enums\CommercialProduct;
use App\Domain\Commercial\Services\AnnualPlanService;
use App\Domain\Commercial\Services\CommercialPlanReport;
use App\Domain\Commercial\Services\OilTankService;
use App\Domain\HumanResources\Services\LaborCostReport;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\SstFinding;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * El tablero de Dirección: lo que cada área ya calcula en su pantalla, junto, mes a mes.
 *
 *  - Planta: fruta procesada, kWh, disponibilidad y eficiencia (los KPI mensuales).
 *  - Comercial: aceite despachado, facturado y cartera.
 *  - Costos: personal (el mismo cálculo del tablero «Costo de personal»: jornales,
 *    novedades pagadas, recargos, horas extras y alimentación, sin prestaciones) y
 *    mantenimiento (el costo real de las OT cerradas).
 *  - El costo de transformación por tonelada de fruta procesada: personal + mantenimiento.
 *    No es un margen: la compra de fruta, el costo más grande, no está en la plataforma.
 *  - SST: hallazgos abiertos y vencidos.
 *
 * Solo totales: ningún sueldo de una persona sale de aquí.
 */
class DirectionDashboard
{
    public function __construct(
        private readonly LaborCostReport $labor,
        private readonly MaintenanceCostReportService $maintenance,
        private readonly CommercialPlanReport $commercial,
        private readonly OilTankService $tanks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, int $year, int $month): array
    {
        $tenantId = (string) $tenant->getKey();
        $today = CarbonImmutable::parse(CarbonImmutable::now($tenant->plantTimezone() ?? 'America/Bogota')->toDateString());
        $plants = Plant::withoutGlobalScopes()->where('tenant_id', $tenantId)->get();

        $kpis = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->get()
            ->groupBy('month');

        $oil = $this->commercial->monthTotals($this->commercial->dispatched($tenantId, $year, CommercialProduct::Cpo));
        $invoiced = array_fill(1, 12, 0.0);

        foreach (CommercialProduct::cases() as $product) {
            foreach ($this->commercial->invoicedByMonth($tenantId, $year, $product) as $months) {
                foreach ($months as $m => $value) {
                    $invoiced[$m] = round($invoiced[$m] + $value['amount'], 2);
                }
            }
        }

        $months = [];

        foreach (range(1, 12) as $m) {
            $start = CarbonImmutable::create($year, $m, 1);
            $isFuture = $start->greaterThan($today);
            $monthKpis = $kpis->get($m, collect());
            $tons = (float) $monthKpis->sum('processed_tons');
            $personnel = $isFuture ? null : $this->personnel($tenant, $start, $today)['totals']['total'];
            $maintenance = $isFuture ? null : $this->maintenance($tenantId, $plants, $year, $m);
            $cost = $personnel === null ? null : round($personnel + $maintenance['total'], 2);

            $months[$m] = [
                'tons' => round($tons, 2),
                'kwh' => round((float) $monthKpis->sum('kwh_total'), 0),
                'kwh_per_ton' => $tons > 0 ? (float) $monthKpis->sum('kwh_total') / $tons : null,
                'availability' => $monthKpis->avg('availability_percentage'),
                'efficiency' => $monthKpis->avg('efficiency_percentage'),
                'oil' => $oil[$m],
                'invoiced' => $invoiced[$m],
                'personnel' => $personnel,
                'maintenance' => $maintenance['total'] ?? null,
                'maintenance_budget' => $maintenance['budget'] ?? null,
                'cost' => $cost,
                'cost_per_ton' => $cost !== null && $tons > 0 ? $cost / $tons : null,
                'future' => $isFuture,
            ];
        }

        $ytd = array_filter($months, fn (int $m): bool => $m <= $month, ARRAY_FILTER_USE_KEY);
        $sum = fn (string $key): float => round(array_sum(array_map(fn (array $row): float => (float) ($row[$key] ?? 0), $ytd)), 2);
        $ytdTons = $sum('tons');
        $ytdCost = $sum('cost');

        $selected = CarbonImmutable::create($year, $month, 1);
        $personnelMonth = $selected->greaterThan($today) ? null : $this->personnel($tenant, $selected, $today);
        $maintenanceMonth = $selected->greaterThan($today) ? null : $this->maintenance($tenantId, $plants, $year, $month);
        $aging = $this->commercial->aging($tenantId);
        $mix = $this->commercial->marketMix(app(AnnualPlanService::class)->current($tenantId, $year), $tenantId, $year, $month);

        return [
            'year' => $year,
            'month' => $month,
            'months' => $months,
            'current' => $months[$month],
            'ytd' => [
                'tons' => $ytdTons,
                'kwh' => $sum('kwh'),
                'kwh_per_ton' => $ytdTons > 0 ? $sum('kwh') / $ytdTons : null,
                'oil' => $sum('oil'),
                'invoiced' => $sum('invoiced'),
                'personnel' => $sum('personnel'),
                'maintenance' => $sum('maintenance'),
                'maintenance_budget' => $sum('maintenance_budget'),
                'cost' => $ytdCost,
                'cost_per_ton' => $ytdTons > 0 ? $ytdCost / $ytdTons : null,
            ],
            'personnel_areas' => collect($personnelMonth['areas'] ?? [])
                ->map(fn (array $area): array => ['label' => $area['label'], 'color' => $area['color'], 'people' => $area['people'], 'total' => $area['total']])
                ->sortByDesc('total')->values()->all(),
            'personnel_concepts' => collect(LaborCostReport::CONCEPTS)
                ->map(fn (string $label, string $key): array => ['label' => $label, 'color' => LaborCostReport::CONCEPT_COLORS[$key], 'total' => (float) ($personnelMonth['totals'][$key] ?? 0)])
                ->values()->all(),
            'personnel_estimated' => (bool) ($personnelMonth['estimated'] ?? false),
            'maintenance_month' => $maintenanceMonth,
            'mix' => $mix,
            'receivable' => round(array_sum(array_column($aging, 'total')), 2),
            'overdue' => round(array_sum(array_column($aging, 'overdue')), 2),
            'sst_open' => SstFinding::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->open()->count(),
            'sst_overdue' => SstFinding::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('deleted_at')->overdue($today)->count(),
            'tanks' => $this->tanks->latest($tenantId),
        ];
    }

    /**
     * El costo de personal de un mes, hasta hoy si es el mes en curso. Los meses cerrados
     * se guardan unas horas: recalcularlos en cada visita reconstruye la asistencia de
     * todos los trabajadores, doce veces.
     */
    private function personnel(Tenant $tenant, CarbonImmutable $start, CarbonImmutable $today): array
    {
        $end = $start->endOfMonth()->startOfDay();
        $isOpen = $end->greaterThanOrEqualTo($today);
        $to = $isOpen ? $today : $end;

        return Cache::remember(
            "direccion:personal:{$tenant->getKey()}:{$start->format('Y-m')}:{$to->toDateString()}",
            $isOpen ? now()->addMinutes(10) : now()->addHours(6),
            // Solo los totales: el detalle por persona no sale de aquí ni se guarda.
            fn (): array => collect($this->labor->forRange($tenant, $start, $to))->only(['areas', 'totals', 'estimated'])->all(),
        );
    }

    /** @return array{total: float, budget: ?float, labor: float, parts: float, consumables: float, external: float, work_orders: int} */
    private function maintenance(string $tenantId, $plants, int $year, int $month): array
    {
        $total = ['total' => 0.0, 'budget' => null, 'labor' => 0.0, 'parts' => 0.0, 'consumables' => 0.0, 'external' => 0.0, 'work_orders' => 0];

        foreach ($plants as $plant) {
            $report = $this->maintenance->monthlyReport($tenantId, $plant->id, $year, $month);

            foreach (['total', 'labor', 'parts', 'consumables', 'external'] as $key) {
                $total[$key] = round($total[$key] + $report[$key], 2);
            }

            $total['work_orders'] += $report['work_order_count'];
            $total['budget'] = $report['budget'] === null ? $total['budget'] : round(($total['budget'] ?? 0) + $report['budget'], 2);
        }

        return $total;
    }
}
