<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Pages;

use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use App\Filament\Widgets\Lists\MaintenanceRequestKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; cada una filtra la lista al tocarla. */
    protected function getHeaderWidgets(): array
    {
        return [MaintenanceRequestKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
