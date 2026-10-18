<?php

namespace App\Filament\Resources\Inventory\InventoryCount\Pages;

use App\Filament\Resources\Inventory\InventoryCount\InventoryCountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryCounts extends ListRecords
{
    protected static string $resource = InventoryCountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Abrir conteo'),
        ];
    }
}
