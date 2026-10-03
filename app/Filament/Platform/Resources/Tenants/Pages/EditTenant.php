<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Filament\Resources\Tenants\Schemas\TenantForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Archivar se puede deshacer; el borrado definitivo de una empresa —sus equipos,
     * OT, paros, nómina— no, y no tiene sitio en un botón al lado de «Guardar». Para
     * dejar de atender a un cliente está «Suspender».
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Archivar')
                ->modalHeading(fn (): string => "Archivar {$this->record->name}")
                ->modalDescription('Deja de aparecer en las listas y nadie de la empresa puede entrar. Sus datos se conservan y se puede restaurar.')
                ->modalSubmitActionLabel('Archivar')
                ->successNotificationTitle('Empresa archivada'),
            RestoreAction::make()
                ->successNotificationTitle('Empresa restaurada'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TenantForm::withAccessFromStatus($data);
    }

    protected function getRedirectUrl(): string
    {
        return TenantResource::getUrl('view', ['record' => $this->record]);
    }
}
