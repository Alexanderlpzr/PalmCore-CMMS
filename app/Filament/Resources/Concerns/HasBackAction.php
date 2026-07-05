<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * A "Volver" header action back to the resource's list page. The breadcrumb
 * trail alone wasn't enough of an affordance for users to feel oriented on
 * View/Edit pages, so every such page gets an explicit, consistently-placed
 * way back.
 */
trait HasBackAction
{
    protected function getBackAction(): Action
    {
        return Action::make('back')
            ->label('Volver')
            ->tooltip('Volver al listado')
            ->icon(Heroicon::OutlinedArrowLeft)
            ->color('gray')
            ->url(static::getResource()::getUrl('index'));
    }
}
