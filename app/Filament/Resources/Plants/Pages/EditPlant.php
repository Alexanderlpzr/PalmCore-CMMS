<?php

namespace App\Filament\Resources\Plants\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Plants\PlantResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPlant extends EditRecord
{
    use HasBackAction;

    protected static string $resource = PlantResource::class;

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
