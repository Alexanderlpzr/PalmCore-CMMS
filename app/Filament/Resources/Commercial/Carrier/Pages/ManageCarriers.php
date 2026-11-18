<?php

namespace App\Filament\Resources\Commercial\Carrier\Pages;

use App\Filament\Resources\Commercial\Carrier\CarrierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCarriers extends ManageRecords
{
    protected static string $resource = CarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva transportadora'),
        ];
    }
}
