<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class MttrTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Tendencia MTTR';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        return 'Tiempo Medio de Reparación (horas) — calculado mensualmente. Gaps indican meses sin fallas.';
    }

    protected function getData(): array
    {
        $points = app(AnalyticsService::class)->mttrTrend(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'MTTR (h)',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(168, 85, 247, 1)',
                    'spanGaps' => false,
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
