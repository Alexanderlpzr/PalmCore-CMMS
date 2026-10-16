<?php

namespace App\Filament\Widgets\Lists;

use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Filament\Widgets\ListKpis;
use App\Models\SparePart;
use App\Models\Tenant;
use Filament\Facades\Filament;

/** Encima de los repuestos: lo que hay que reponer, lo que se acabó y lo que vale el inventario. */
class SparePartKpis extends ListKpis
{
    protected function kpis(): array
    {
        /** @var Tenant $tenant */
        $tenant = Filament::getTenant();
        $attention = app(WarehouseDashboard::class)->attention($tenant);
        $active = SparePart::query()->where('is_active', true)->count();

        return [
            [
                'label' => 'Repuestos activos',
                'value' => number_format($active, 0, ',', '.'),
                'detail' => 'En el catálogo',
                'icon' => 'heroicon-o-wrench-screwdriver',
            ],
            [
                'label' => 'En el mínimo o por debajo',
                'value' => number_format($attention['belowMinimum'], 0, ',', '.'),
                'detail' => 'Hay que pedirlos',
                'icon' => 'heroicon-o-arrow-trending-down',
                'tone' => $attention['belowMinimum'] > 0 ? 'warning' : 'ok',
                'percent' => $active > 0 ? $attention['belowMinimum'] * 100 / $active : null,
                'color' => '#f59e0b',
                'link' => ['filters' => ['bajo_minimo' => ['isActive' => true]]],
            ],
            [
                'label' => 'Sin existencias',
                'value' => number_format($attention['outOfStock'], 0, ',', '.'),
                'detail' => 'En cero en todos los almacenes',
                'icon' => 'heroicon-o-archive-box-x-mark',
                'tone' => $attention['outOfStock'] > 0 ? 'danger' : 'ok',
                'link' => ['filters' => ['sin_existencias' => ['isActive' => true]]],
            ],
            [
                'label' => 'Valor del inventario',
                'value' => '$ '.number_format($attention['inventoryValue'], 0, ',', '.'),
                'detail' => 'Existencias al costo promedio',
                'icon' => 'heroicon-o-banknotes',
            ],
        ];
    }

    protected function listUrl(array $parameters = []): string
    {
        return SparePartResource::getUrl('index', $parameters);
    }
}
