<?php

namespace App\Filament\Resources\MaintenanceBudgets\Pages;

use App\Filament\Resources\MaintenanceBudgets\MaintenanceBudgetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceBudget extends EditRecord
{
    protected static string $resource = MaintenanceBudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
