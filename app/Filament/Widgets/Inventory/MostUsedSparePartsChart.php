<?php

namespace App\Filament\Widgets\Inventory;

use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Filament\Pages\TableroAlmacen;
use App\Filament\Widgets\Concerns\DiceCuandoNoHayDatos;
use App\Models\Tenant;
use App\Support\FrondaPalette;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Los repuestos que más salieron en los últimos 90 días, por cantidad: los que no pueden
 * faltar en el estante.
 */
class MostUsedSparePartsChart extends ChartWidget
{
    use DiceCuandoNoHayDatos;

    protected ?string $heading = 'Repuestos más usados';

    protected ?string $description = 'Cantidad que salió en los últimos 90 días.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return TableroAlmacen::canAccess();
    }

    protected function getData(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $parts = app(WarehouseDashboard::class)->mostUsed($tenant);

        return [
            'datasets' => [[
                'label' => 'Cantidad',
                'data' => array_values($parts),
                'backgroundColor' => FrondaPalette::Petrol[600],
                'borderRadius' => 3,
            ]],
            'labels' => array_map(fn (string $name): string => mb_strimwidth($name, 0, 32, '…'), array_keys($parts)),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
