<?php

namespace App\Filament\Platform\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ObservabilityPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Observabilidad';

    protected static string|\UnitEnum|null $navigationGroup = 'Observabilidad';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.platform.observability';
}
