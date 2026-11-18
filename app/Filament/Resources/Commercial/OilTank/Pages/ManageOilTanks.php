<?php

namespace App\Filament\Resources\Commercial\OilTank\Pages;

use App\Filament\Resources\Commercial\OilTank\OilTankResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOilTanks extends ManageRecords
{
    protected static string $resource = OilTankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo tanque'),
        ];
    }
}
