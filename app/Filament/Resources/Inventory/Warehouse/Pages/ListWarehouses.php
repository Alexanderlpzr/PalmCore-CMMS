<?php

namespace App\Filament\Resources\Inventory\Warehouse\Pages;

use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
