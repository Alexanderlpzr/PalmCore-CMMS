<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class CostByEquipmentWidget extends ChartWidget
{
    protected ?string $heading = 'Costo por Equipo';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '300px';

    public function getDescription(): ?string
    {
        return 'Top 10 equipos con mayor costo acumulado de órdenes de trabajo (costo total real).';
    }

    protected function getData(): array
    {
        $points = app(AnalyticsService::class)->costByEquipment(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'Costo total',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
