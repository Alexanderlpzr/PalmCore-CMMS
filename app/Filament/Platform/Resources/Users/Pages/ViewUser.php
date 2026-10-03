<?php

namespace App\Filament\Platform\Resources\Users\Pages;

use App\Filament\Platform\Resources\Users\UserActions;
use App\Filament\Platform\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * La ficha de una persona: sus datos arriba, y en pestañas sus empresas y roles, sus
 * ingresos, las veces que alguien entró como ella y lo que se le cambió.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make(UserActions::all())
                ->label('Acciones')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->button(),
        ];
    }
}
