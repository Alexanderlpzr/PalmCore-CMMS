<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class AvailabilityTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Tendencia de Disponibilidad';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        return 'Disponibilidad promedio de la flota — últimos 12 meses.';
    }

    protected function getData(): array
    {
        $trends = app(ExecutiveDashboardService::class)->trends(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'Disponibilidad (%)',
                    'data' => array_map(fn (array $t) => $t['availability'], $trends),
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(34, 197, 94, 1)',
                    'spanGaps' => false,
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
