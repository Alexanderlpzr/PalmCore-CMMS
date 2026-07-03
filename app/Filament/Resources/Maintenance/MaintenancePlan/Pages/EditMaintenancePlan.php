<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenancePlan extends EditRecord
{
    use HasBackAction;

    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }
}
