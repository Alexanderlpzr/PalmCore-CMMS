<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    use HasBackAction;

    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless($this->record->isEditable(), 403, 'Esta OT no puede editarse en su estado actual.');
    }
}
