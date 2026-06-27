<?php

namespace App\Filament\Platform\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class BackupsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Backups';

    protected static string|\UnitEnum|null $navigationGroup = 'Observabilidad';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.platform.backups';
}
