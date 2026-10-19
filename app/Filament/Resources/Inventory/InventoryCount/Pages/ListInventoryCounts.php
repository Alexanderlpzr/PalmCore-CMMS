<?php

namespace App\Filament\Resources\Inventory\InventoryCount\Pages;

use App\Filament\Resources\Inventory\InventoryCount\InventoryCountResource;
use App\Filament\Widgets\Lists\InventoryCountKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryCounts extends ListRecords
{
    protected static string $resource = InventoryCountResource::class;

    /** Lo que dicen los conteos en cuatro tarjetas; los abiertos filtran la lista al tocarla. */
    protected function getHeaderWidgets(): array
    {
        return [InventoryCountKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Abrir conteo'),
        ];
    }
}
