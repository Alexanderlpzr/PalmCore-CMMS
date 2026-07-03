<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplier extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
            $this->getBackAction(),
        ];
    }
}
