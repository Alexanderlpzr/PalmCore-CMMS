<?php

namespace App\Filament\Resources\Manufacturers\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewManufacturer extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = ManufacturerResource::class;

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
