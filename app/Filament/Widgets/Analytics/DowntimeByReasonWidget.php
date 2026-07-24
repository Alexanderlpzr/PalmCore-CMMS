<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Horas de parada por Tipo II — la causa concreta (falla mecánica, atascamiento,
 * falta de fruta esterilizada…). Es el «Horas no procesadas por tipo» del Excel.
 */
class DowntimeByReasonWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    /** Paleta que se recicla sobre las porciones del pie. */
    private const PALETTE = [
        'rgba(59, 130, 246, 0.8)',
        'rgba(239, 68, 68, 0.8)',
        'rgba(16, 185, 129, 0.8)',
        'rgba(168, 85, 247, 0.8)',
        'rgba(249, 115, 22, 0.8)',
        'rgba(14, 165, 233, 0.8)',
        'rgba(234, 179, 8, 0.8)',
        'rgba(100, 116, 139, 0.8)',
    ];

    protected ?string $heading = 'Horas no Procesadas por Tipo II';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        return 'La causa concreta del paro — '.DashboardPeriod::label($this->pageFilters).'.';
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $points = app(AnalyticsService::class)->downtimeByReason(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => array_map(
                        fn (int $i): string => self::PALETTE[$i % count(self::PALETTE)],
                        array_keys($points),
                    ),
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
