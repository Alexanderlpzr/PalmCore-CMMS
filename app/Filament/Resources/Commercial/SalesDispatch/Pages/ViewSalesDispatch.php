<?php

namespace App\Filament\Resources\Commercial\SalesDispatch\Pages;

use App\Filament\Resources\Commercial\SalesDispatch\DispatchActions;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** La ficha del cargue, con el paso que le toca —descargue, factura o pago— arriba. */
class ViewSalesDispatch extends ViewRecord
{
    protected static string $resource = SalesDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DispatchActions::unload(),
            DispatchActions::invoice(),
            DispatchActions::pay(),
            DispatchActions::editInvoice(),
            EditAction::make()->color('gray'),
        ];
    }
}
