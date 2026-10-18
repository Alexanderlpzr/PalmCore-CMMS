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
 * Lo que salió del almacén cada mes, en pesos: salidas sueltas y consumo de órdenes de
 * trabajo, los últimos seis meses.
 */
class MonthlyOutgoingValueChart extends ChartWidget
{
    use DiceCuandoNoHayDatos;

    protected ?string $heading = 'Salidas del almacén por mes';

    protected ?string $description = 'Valor al costo de lo que salió, los últimos seis meses.';

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
        $data = app(WarehouseDashboard::class)->monthlyOutgoingValue($tenant);

        return [
            'datasets' => [[
                'label' => 'Valor de las salidas',
                'data' => $data['values'],
                'backgroundColor' => FrondaPalette::Brand[600],
                'borderRadius' => 3,
            ]],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
