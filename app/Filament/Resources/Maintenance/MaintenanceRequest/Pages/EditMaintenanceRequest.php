<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Pages;

use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceRequest extends EditRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->record->isEditable(), 403, 'Esta solicitud no puede editarse en su estado actual.');
    }
}
