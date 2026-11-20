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
 * Los repuestos que más salieron en los últimos 90 días, por cantidad: los que no pueden
 * faltar en el estante.
 */
class MostUsedSparePartsChart extends ChartWidget
{
    use DespliegaDetalle;
    use DiceCuandoNoHayDatos;

    /** Tocar un repuesto abre su ficha: salidas, plata, OT y la última salida (2026-11-19). */
    protected string $view = 'filament.widgets.grafica-con-detalle';

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

    public function detalles(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $service = app(WarehouseDashboard::class);
        $parts = $service->mostUsed($tenant);
        $details = $service->partDetails($tenant, array_keys($parts));
        $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');

        return array_map(function (string $name, float $quantity) use ($details, $pesos): array {
            $detail = $details[$name] ?? null;

            return self::ficha($name, [
                'Cantidad' => rtrim(rtrim(number_format($quantity, 2, ',', '.'), '0'), ','),
                'Salidas' => (string) ($detail['movements'] ?? 0),
                'Valor al costo' => $pesos((float) ($detail['value'] ?? 0)),
                'Órdenes de trabajo' => (string) ($detail['workOrders'] ?? 0),
                'Última salida' => ($detail['lastOut'] ?? null)?->format('d/m/Y') ?? '—',
            ]);
        }, array_keys($parts), array_values($parts));
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
