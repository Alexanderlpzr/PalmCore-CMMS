<?php

namespace App\Filament\Resources\Commercial\FruitSupplier\Pages;

use App\Filament\Resources\Commercial\FruitSupplier\FruitSupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFruitSuppliers extends ManageRecords
{
    protected static string $resource = FruitSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo proveedor'),
        ];
    }
}
