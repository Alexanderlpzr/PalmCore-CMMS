<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Services\MaintenanceCostReportService;
use App\Domain\Analytics\Services\MonthlyProductionComparison;
use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Pages\Dashboard;
use App\Models\Plant;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Livewire\Attributes\Reactive;

/**
 * Lo primero del Dashboard: cinco cifras del período y las pestañas. Cada tarjeta abre su
 * pestaña —producción, paros, confiabilidad, costos—, donde están las gráficas; así el
 * tablero cabe en una pantalla y se ve más gráfica que tabla (pedido 2026-11-19).
 *
 * Es un widget y no parte de la página porque los filtros solo vuelven a dibujar los
 * widgets: dentro de la página, las cifras se quedarían con la planta y el período de antes.
 */
class DashboardSummaryWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard-resumen';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    /** La pestaña que se mira; la pasa {@see Dashboard::getWidgetData()}. */
    #[Reactive]
    public string $seccion = Dashboard::DEFAULT_SECTION;

    /**
     * @return array{period: string, cards: list<array{section: string, label: string, value: string, detail: string, icon: string, tone: string}>, sections: array<string, string>}
     */
    public function summary(): array
    {
        $tenantId = (string) Filament::getTenant()?->getKey();
        $plant = $this->plant($tenantId);
        [$from, $to] = DashboardPeriod::snapshotWindow($this->pageFilters);
        $from = CarbonImmutable::parse($from);
        $to = CarbonImmutable::parse($to);
        $number = fn (?float $value, int $decimals = 0): string => $value === null ? '—' : number_format($value, $decimals, ',', '.');

        $kpi = $plant ? app(PlantKpiService::class)->calculate($plant, $from, $to) : null;
        $production = $plant ? app(MonthlyProductionComparison::class)->forWindow($tenantId, $plant->id, $from, $to) : null;
        $compliance = app(AnalyticsService::class)->preventiveCompliance($tenantId)['compliance'] ?? null;
        $cost = $plant ? $this->cost($tenantId, $plant->id, $from, $to) : null;

        $change = $production['change'] ?? null;
        $availability = $kpi['availability_percentage'] ?? null;

        return [
            'period' => DashboardPeriod::label($this->pageFilters),
            'sections' => Dashboard::SECTIONS,
            'cards' => [
                [
                    'section' => 'produccion',
                    'label' => 'Producción',
                    'value' => ($production['tons'] ?? null) !== null ? $number($production['tons']).' t' : 'Sin dato',
                    'detail' => match (true) {
                        $change === null => 'Sin el mismo período del año anterior',
                        default => ($change >= 0 ? '▲ ' : '▼ ').$number(abs($change), 1).' % contra '.($from->year - 1)
                            .(($production['includesCurrentMonth'] ?? false) ? ' · mes en curso' : ''),
                    },
                    'icon' => 'heroicon-o-truck',
                    'tone' => $change === null ? 'neutral' : ($change >= 0 ? 'ok' : 'warning'),
                ],
                [
                    'section' => 'produccion',
                    'label' => 'Disponibilidad',
                    'value' => $availability !== null ? $number($availability, 1).' %' : 'Sin dato',
                    'detail' => $kpi !== null ? $number($kpi['maintenance_lost_hours'], 1).' h que le quitó mantenimiento' : 'Sin planta',
                    'icon' => 'heroicon-o-bolt',
                    'tone' => match (true) {
                        $availability === null => 'neutral',
                        $availability >= 90 => 'ok',
                        $availability >= 80 => 'warning',
                        default => 'danger',
                    },
                ],
                [
                    'section' => 'paros',
                    'label' => 'Horas de paro',
                    'value' => $kpi !== null ? $number($kpi['lost_hours'], 1).' h' : 'Sin dato',
                    'detail' => $kpi !== null ? $kpi['failure_count'].' '.($kpi['failure_count'] === 1 ? 'falla' : 'fallas').' de mantenimiento' : 'Sin planta',
                    'icon' => 'heroicon-o-pause-circle',
                    'tone' => 'neutral',
                ],
                [
                    'section' => 'confiabilidad',
                    'label' => 'Preventivos al día',
                    'value' => $compliance !== null ? $number($compliance, 0).' %' : 'Sin planes',
                    'detail' => 'Planes de mantenimiento sin vencer',
                    'icon' => 'heroicon-o-shield-check',
                    'tone' => match (true) {
                        $compliance === null => 'neutral',
                        $compliance >= 90 => 'ok',
                        $compliance >= 70 => 'warning',
                        default => 'danger',
                    },
                ],
                [
                    'section' => 'costos',
                    'label' => 'Gasto de mantenimiento',
                    'value' => $cost !== null ? '$ '.$number($cost['total']) : 'Sin dato',
                    'detail' => $cost !== null ? $cost['orders'].' '.($cost['orders'] === 1 ? 'OT cerrada' : 'OT cerradas') : 'Sin planta',
                    'icon' => 'heroicon-o-banknotes',
                    'tone' => 'neutral',
                ],
            ],
        ];
    }

    /**
     * El gasto de los meses del período, con un tope de doce para no recorrer años.
     *
     * @return array{total: float, orders: int}
     */
    private function cost(string $tenantId, string $plantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $service = app(MaintenanceCostReportService::class);
        $total = 0.0;
        $orders = 0;
        $month = $from->startOfMonth();

        for ($i = 0; $i < 12 && $month->lte($to); $i++, $month = $month->addMonth()) {
            $report = $service->monthlyReport($tenantId, $plantId, $month->year, $month->month);
            $total += (float) $report['total'];
            $orders += (int) $report['work_order_count'];
        }

        return ['total' => $total, 'orders' => $orders];
    }

    private function plant(string $tenantId): ?Plant
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        return Plant::query()
            ->where('tenant_id', $tenantId)
            ->when($plantId, fn ($query) => $query->whereKey($plantId))
            ->orderBy('name')
            ->first();
    }
}
