<?php

namespace App\Filament\Resources\Inventory\Warehouse\Pages;

use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouse extends ViewRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
