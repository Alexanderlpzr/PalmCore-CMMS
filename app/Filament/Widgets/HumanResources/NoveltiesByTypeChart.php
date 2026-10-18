<?php

namespace App\Filament\Widgets\HumanResources;

use App\Domain\HumanResources\Services\HumanResourcesDashboard;
use App\Filament\Pages\TableroTalentoHumano;
use App\Filament\Widgets\Concerns\DiceCuandoNoHayDatos;
use App\Filament\Widgets\Concerns\MuestraLosValores;
use App\Models\Tenant;
use App\Support\FrondaPalette;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Los días de novedad del corte en curso por tipo: incapacidades, vacaciones, permisos,
 * ausencias. Dice de un vistazo dónde se va el tiempo que no se trabajó.
 */
class NoveltiesByTypeChart extends ChartWidget
{
    use DiceCuandoNoHayDatos;
    use MuestraLosValores;

    private const COLORS = [
        FrondaPalette::Brand[600],
        FrondaPalette::Petrol[600],
        FrondaPalette::Brand[400],
        FrondaPalette::Petrol[400],
        '#f59e0b',
        '#ef4444',
        '#94a3b8',
        '#cbd5e1',
    ];

    protected ?string $heading = 'Novedades del corte';

    protected ?string $description = 'Días de incapacidad, vacaciones, permisos y ausencias.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return TableroTalentoHumano::canAccess();
    }

    protected function getData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $byType = app(HumanResourcesDashboard::class)->noveltiesByType($tenant);

        return [
            'datasets' => [[
                'label' => 'Días',
                'data' => array_values($byType),
                'backgroundColor' => array_map(fn (int $i): string => self::COLORS[$i % count(self::COLORS)], array_keys(array_values($byType))),
            ]],
            'labels' => array_keys($byType),
        ];
    }

    protected function valueUnit(): string
    {
        return 'días';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
