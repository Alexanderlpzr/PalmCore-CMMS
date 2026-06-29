<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Analytics Dashboard (KPIs, MTTR, MTBF, disponibilidad, gráficas).
 *
 * This subclass exists only to RELOCATE the stock Filament dashboard: Inicio
 * now owns the panel root (`/admin/{tenant}`), so the analytics view moves to
 * `/admin/{tenant}/dashboard` and into the "Indicadores" navigation group. Its
 * analytics behaviour is inherited verbatim from the framework dashboard — no
 * widget, KPI, or chart logic is changed here.
 */
class Dashboard extends BaseDashboard
{
    protected static string $routePath = '/dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 1;
}
