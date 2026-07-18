<?php

namespace App\Filament\Resources\MaintenanceBudgets\Pages;

use App\Filament\Resources\MaintenanceBudgets\MaintenanceBudgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceBudgets extends ListRecords
{
    protected static string $resource = MaintenanceBudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
