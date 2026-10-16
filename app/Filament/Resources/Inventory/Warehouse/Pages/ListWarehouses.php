<?php

namespace App\Filament\Resources\Inventory\Warehouse\Pages;

use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use App\Filament\Widgets\Lists\WarehouseKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; cada una filtra la lista al tocarla. */
    protected function getHeaderWidgets(): array
    {
        return [WarehouseKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
