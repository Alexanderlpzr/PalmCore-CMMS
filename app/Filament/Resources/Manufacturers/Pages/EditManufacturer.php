<?php

namespace App\Filament\Resources\Manufacturers\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditManufacturer extends EditRecord
{
    use HasBackAction;

    protected static string $resource = ManufacturerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            $this->getBackAction(),
        ];
    }
}
