<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Horas de parada por Sección de planta (Generación eléctrica, Extracción,
 * Palmistería…), como el «Resumen horas de paros por sección» del Excel.
 */
class DowntimeBySectionWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Horas de Parada por Sección';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        return 'En qué sección de la planta — '.DashboardPeriod::label($this->pageFilters).'.';
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $points = app(AnalyticsService::class)->downtimeBySection(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
