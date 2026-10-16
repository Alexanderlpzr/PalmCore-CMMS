<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use App\Filament\Widgets\ListKpis;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Support\FrondaPalette;
use Filament\Facades\Filament;

/** Encima de los almacenes: cuántos hay, lo que vale lo guardado, el movimiento del día y lo que falta. */
class WarehouseKpis extends ListKpis
{
    protected function kpis(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $attention = app(WarehouseDashboard::class)->attention($tenant);

        return [
            [
                'label' => 'Almacenes activos',
                'value' => number_format(Warehouse::query()->where('is_active', true)->count(), 0, ',', '.'),
                'detail' => 'Donde se guardan los repuestos',
                'icon' => 'heroicon-o-building-storefront',
            ],
            [
                'label' => 'Valor del inventario',
                'value' => '$ '.number_format($attention['inventoryValue'], 0, ',', '.'),
                'detail' => 'Todos los almacenes, al costo promedio',
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Movimientos de hoy',
                'value' => number_format($attention['movementsToday'], 0, ',', '.'),
                'detail' => 'Entradas, salidas y ajustes',
                'icon' => 'heroicon-o-arrows-right-left',
            ],
            [
                'label' => 'Repuestos bajo el mínimo',
                'value' => number_format($attention['belowMinimum'], 0, ',', '.'),
                'detail' => 'Ver cuáles en Repuestos',
                'icon' => 'heroicon-o-arrow-trending-down',
                'tone' => $attention['belowMinimum'] > 0 ? 'warning' : 'ok',
                'url' => SparePartResource::getUrl('index', ['filters' => ['bajo_minimo' => ['isActive' => true]]]),
            ],
        ];
    }

    protected function bars(): ?array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $data = app(WarehouseDashboard::class)->monthlyOutgoingValue($tenant);

        return [
            'title' => 'Valor de las salidas por mes',
            'labels' => $data['labels'],
            'series' => [['label' => 'Salidas', 'color' => FrondaPalette::Brand[600], 'values' => $data['values']]],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return WarehouseResource::getUrl('index', $parameters);
    }
}
