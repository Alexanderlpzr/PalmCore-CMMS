<?php

namespace App\Filament\Platform\Resources\ImpersonationLogs\Pages;

use App\Filament\Platform\Resources\ImpersonationLogs\ImpersonationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListImpersonationLogs extends ListRecords
{
    protected static string $resource = ImpersonationLogResource::class;
}
