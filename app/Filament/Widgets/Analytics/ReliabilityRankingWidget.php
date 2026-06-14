<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\DTOs\TrendPoint;
use App\Domain\Analytics\Services\AnalyticsService;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ReliabilityRankingWidget extends ChartWidget
{
    protected ?string $heading = 'Ranking de Confiabilidad';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 16;

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Top 5 mejores (verde) y 5 peores (rojo) equipos por disponibilidad (%).';
    }

    protected function getData(): array
    {
        $ranking = app(AnalyticsService::class)->reliabilityRanking(Filament::getTenant()->id);

        /** @var TrendPoint[] $best */
        $best = $ranking['best'];

        /** @var TrendPoint[] $worst */
        $worst = $ranking['worst'];

        // Best at top, worst at bottom — sorted within each group
        $all = array_merge($best, array_reverse($worst));

        $greenColor = 'rgba(34, 197, 94, 0.8)';
        $redColor = 'rgba(239, 68, 68, 0.8)';

        $colors = array_merge(
            array_fill(0, count($best), $greenColor),
            array_fill(0, count($worst), $redColor),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Disponibilidad (%)',
                    'data' => array_map(fn ($p) => $p->value, $all),
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1,
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $all),
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
            'scales' => [
                'x' => [
                    'min' => 0,
                    'max' => 100,
                    'ticks' => [
                        'callback' => RawJs::make('(v) => v + "%"'),
                    ],
                ],
            ],
        ];
    }
}
