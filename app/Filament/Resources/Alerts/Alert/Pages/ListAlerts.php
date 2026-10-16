<?php

namespace App\Filament\Resources\Alerts\Alert\Pages;

use App\Filament\Resources\Alerts\AlertResource;
use App\Filament\Widgets\Lists\AlertKpis;
use Filament\Resources\Pages\ListRecords;

class ListAlerts extends ListRecords
{
    protected static string $resource = AlertResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; las que filtran lo hacen al tocarlas. */
    protected function getHeaderWidgets(): array
    {
        return [AlertKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
