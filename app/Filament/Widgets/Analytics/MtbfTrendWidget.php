<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

class MtbfTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Tendencia MTBF';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        return 'Tiempo Medio Entre Fallas (horas) — calculado mensualmente. Gaps indican meses sin fallas.';
    }

    protected function getData(): array
    {
        $points = app(AnalyticsService::class)->mtbfTrend(Filament::getTenant()->id);

        return [
            'datasets' => [
                [
                    'label' => 'MTBF (h)',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(59, 130, 246, 1)',
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
