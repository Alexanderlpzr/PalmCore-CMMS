<?php

namespace App\Filament\Platform\Resources\LoginLogs\Pages;

use App\Filament\Platform\Resources\LoginLogs\LoginLogResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginLogs extends ListRecords
{
    protected static string $resource = LoginLogResource::class;
}
