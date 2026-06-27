<?php

namespace App\Filament\Platform\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class GlobalConfigPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configuración Global';

    protected static string|\UnitEnum|null $navigationGroup = 'Integraciones';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.platform.global-config';
}
