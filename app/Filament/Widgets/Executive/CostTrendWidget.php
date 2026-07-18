<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class CostTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Tendencia de Costo';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        return 'Costo de órdenes de trabajo completadas por mes — últimos 12 meses.';
    }

    protected function getData(): array
    {
        $trends = app(ExecutiveDashboardService::class)->trends(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'Costo',
                    'data' => array_map(fn (array $t) => $t['cost'], $trends),
                    'borderColor' => 'rgba(100, 116, 139, 1)',
                    'backgroundColor' => 'rgba(100, 116, 139, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(100, 116, 139, 1)',
                ],
            ],
            'labels' => array_map(fn (array $t) => $t['month'], $trends),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
