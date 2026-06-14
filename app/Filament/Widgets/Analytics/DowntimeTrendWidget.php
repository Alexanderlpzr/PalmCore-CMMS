<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class DowntimeTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Tendencia de Paradas';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        return 'Horas totales de parada por mes — últimos 12 meses.';
    }

    protected function getData(): array
    {
        $points = app(AnalyticsService::class)->downtimeTrend(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'borderColor' => 'rgba(249, 115, 22, 1)',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(249, 115, 22, 1)',
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
