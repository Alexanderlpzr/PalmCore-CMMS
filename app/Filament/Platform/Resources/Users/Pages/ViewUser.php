<?php

namespace App\Filament\Platform\Resources\Users\Pages;

use App\Filament\Platform\Resources\Users\UserActions;
use App\Filament\Platform\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * La ficha de una persona: sus datos arriba, y en pestañas sus empresas y roles, sus
 * ingresos, las veces que alguien entró como ella y lo que se le cambió.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /** El nombre de la persona, no «Ver Ana Portería». */
    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar datos'),
            ActionGroup::make(UserActions::all())
                ->label('Acciones')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
