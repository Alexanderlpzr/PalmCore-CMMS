<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CostTrendWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tendencia de Costo';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        $period = DashboardPeriod::label($this->pageFilters);

        return "Costo de órdenes de trabajo completadas por mes — {$period}.";
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $trend = app(ExecutiveDashboardService::class)->costTrend(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Costo',
                    'data' => array_map(fn (array $t) => $t['cost'], $trend),
                    'borderColor' => 'rgba(100, 116, 139, 1)',
                    'backgroundColor' => 'rgba(100, 116, 139, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(100, 116, 139, 1)',
                ],
            ],
            'labels' => array_map(fn (array $t) => $t['month'], $trend),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
