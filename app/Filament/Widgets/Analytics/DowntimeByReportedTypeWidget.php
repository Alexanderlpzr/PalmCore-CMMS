<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Horas de parada por "Tipo I" (quién paró la línea, no qué se rompió):
 * programada, mantenimiento, operativa, externa. Responde la pregunta que la
 * planilla en papel de la planta ya se hace, con las mismas cuatro categorías.
 */
class DowntimeByReportedTypeWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    private const COLORS = [
        'Programada' => 'rgba(59, 130, 246, 0.8)',
        'Mantenimiento' => 'rgba(249, 115, 22, 0.8)',
        'Operativa' => 'rgba(100, 116, 139, 0.8)',
        'Externa' => 'rgba(168, 85, 247, 0.8)',
    ];

    protected ?string $heading = 'Horas de Parada por Tipo';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '260px';

    public function getDescription(): ?string
    {
        $period = DashboardPeriod::label($this->pageFilters);

        return "Quién paró la línea — {$period}.";
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $points = app(AnalyticsService::class)->downtimeByReportedType(Filament::getTenant()->id, $from, $to);

        return [
            'datasets' => [
                [
                    'label' => 'Horas de parada',
                    'data' => array_map(fn ($p) => $p->value, $points),
                    'backgroundColor' => array_map(
                        fn ($p) => self::COLORS[$p->label] ?? 'rgba(148, 163, 184, 0.8)',
                        $points,
                    ),
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $points),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
