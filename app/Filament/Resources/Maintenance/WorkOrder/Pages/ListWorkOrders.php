<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
