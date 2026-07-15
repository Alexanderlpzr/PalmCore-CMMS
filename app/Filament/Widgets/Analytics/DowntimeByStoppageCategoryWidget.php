<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Horas de parada por causa física (Tipo II): mecánico, eléctrico, falta de
 * fruta… El complemento de DowntimeByReportedTypeWidget — ese responde quién
 * paró la línea, este responde qué se rompió.
 */
class DowntimeByStoppageCategoryWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    private const COLORS = [
        'Mecánico' => 'rgba(239, 68, 68, 0.8)',
        'Eléctrico' => 'rgba(220, 38, 38, 0.8)',
        'Instrumentación' => 'rgba(248, 113, 113, 0.8)',
        'Proceso' => 'rgba(245, 158, 11, 0.8)',
        'Operacional' => 'rgba(251, 191, 36, 0.8)',
        'Falta de fruta' => 'rgba(148, 163, 184, 0.8)',
        'Servicios industriales' => 'rgba(100, 116, 139, 0.8)',
        'Externo' => 'rgba(71, 85, 105, 0.8)',
        'Programado' => 'rgba(59, 130, 246, 0.8)',
        'Otro' => 'rgba(203, 213, 225, 0.8)',
    ];

    protected ?string $heading = 'Horas de Parada por Causa';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 19;

    protected int|string|array $columnSpan = 'half';

    public function getDescription(): ?string
    {
        $period = DashboardPeriod::label($this->pageFilters);

        return "Qué se rompió — {$period}.";
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->pageFilters);
        $points = app(AnalyticsService::class)->downtimeByStoppageCategory(Filament::getTenant()->id, $from, $to);

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
        return 'pie';
    }
}
