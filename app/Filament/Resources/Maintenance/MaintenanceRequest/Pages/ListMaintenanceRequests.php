<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Pages;

use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
