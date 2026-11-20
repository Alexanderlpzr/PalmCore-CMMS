<?php

namespace App\Filament\Widgets\Inventory;

use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Filament\Pages\TableroAlmacen;
use App\Filament\Widgets\Concerns\DespliegaDetalle;
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
    use DespliegaDetalle;
    use DiceCuandoNoHayDatos;

    /** Tocar un mes abre su ficha: a OT, con vale y cuántas salidas (2026-11-19). */
    protected string $view = 'filament.widgets.grafica-con-detalle';

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

    public function detalles(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');

        return array_map(fn (array $mes): array => self::ficha($mes['label'], [
            'Total' => $pesos($mes['value']),
            'A órdenes de trabajo' => $pesos($mes['toWorkOrders']),
            'Con vale de salida' => $pesos($mes['withVoucher']),
            'Salidas' => (string) $mes['movements'],
        ]), app(WarehouseDashboard::class)->monthlyOutgoingDetails($tenant));
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
