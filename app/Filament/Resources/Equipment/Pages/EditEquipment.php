<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipment extends EditRecord
{
    use HasBackAction;

    protected static string $resource = EquipmentResource::class;

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
