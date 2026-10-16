<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Widgets\Lists\EquipmentKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipment extends ListRecords
{
    protected static string $resource = EquipmentResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; cada una filtra la lista al tocarla. */
    protected function getHeaderWidgets(): array
    {
        return [EquipmentKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
