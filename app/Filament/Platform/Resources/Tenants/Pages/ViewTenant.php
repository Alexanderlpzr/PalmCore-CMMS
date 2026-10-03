<?php

namespace App\Filament\Platform\Resources\Tenants\Pages;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Filament\Resources\Tenants\Tables\TenantsTable;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * La ficha de una empresa: plan y acceso arriba, y en pestañas su gente y sus
 * plantas. Las acciones son las mismas que en la fila del listado.
 */
class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    /** El nombre de la empresa, no «Ver empresa». */
    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar datos'),
            ActionGroup::make([
                TenantsTable::impersonateOwnerAction(),
                TenantsTable::suspendAction(),
                TenantsTable::reactivateAction(),
            ])
                ->label('Acciones')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
