<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Facades\Filament;
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

    /**
     * «Volver» en un listado, que no tiene un listado al que volver: regresa a la
     * pantalla anterior, como la flecha del navegador pero a la vista.
     *
     * Si se llegó a la pantalla directamente —un enlace, una pestaña nueva— no hay
     * pantalla anterior dentro de Fronda, y se va al inicio del panel en vez de sacar a
     * la persona del sitio.
     */
    protected function getHistoryBackAction(): Action
    {
        $fallback = json_encode(Filament::getUrl());

        return Action::make('back')
            ->label('Volver')
            ->tooltip('Volver a la pantalla anterior')
            ->icon(Heroicon::OutlinedArrowLeft)
            ->color('gray')
            ->alpineClickHandler(
                "document.referrer.startsWith(window.location.origin) ? window.history.back() : (window.location.href = {$fallback})"
            );
    }
}
