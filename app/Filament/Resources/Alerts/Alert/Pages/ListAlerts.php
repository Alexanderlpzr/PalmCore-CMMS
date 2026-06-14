<?php

namespace App\Filament\Resources\Alerts\Alert\Pages;

use App\Filament\Resources\Alerts\AlertResource;
use Filament\Resources\Pages\ListRecords;

class ListAlerts extends ListRecords
{
    protected static string $resource = AlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
