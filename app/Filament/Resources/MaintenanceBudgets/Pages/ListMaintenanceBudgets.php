<?php

namespace App\Filament\Resources\MaintenanceBudgets\Pages;

use App\Filament\Resources\MaintenanceBudgets\MaintenanceBudgetResource;
use App\Filament\Widgets\Lists\MaintenanceBudgetKpis;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceBudgets extends ListRecords
{
    protected static string $resource = MaintenanceBudgetResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; las que filtran lo hacen al tocarlas. */
    protected function getHeaderWidgets(): array
    {
        return [MaintenanceBudgetKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
