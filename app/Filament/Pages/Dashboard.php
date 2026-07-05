<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\Support\DashboardPeriod;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Analytics Dashboard (KPIs, MTTR, MTBF, disponibilidad, gráficas).
 *
 * This subclass exists to RELOCATE the stock Filament dashboard (Inicio now
 * owns the panel root) AND to add a period filter (año / mes / rango de
 * meses) that the trend widgets (MtbfTrendWidget, MttrTrendWidget,
 * DowntimeTrendWidget, FailuresByMonthWidget) read via
 * InteractsWithPageFilters. Everything else is inherited verbatim from the
 * framework dashboard.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Indicadores';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = 1;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('preset')
                    ->label('Periodo')
                    ->options([
                        DashboardPeriod::DEFAULT_PRESET => 'Últimos 12 meses',
                        'year' => 'Año completo',
                        'month' => 'Un mes',
                        'range' => 'Rango de meses',
                    ])
                    ->default(DashboardPeriod::DEFAULT_PRESET)
                    ->live()
                    ->selectablePlaceholder(false),

                Select::make('year')
                    ->label('Año')
                    ->options(DashboardPeriod::yearOptions())
                    ->default(now()->year)
                    ->visible(fn (Get $get): bool => in_array($get('preset'), ['year', 'month'], strict: true)),

                Select::make('month')
                    ->label('Mes')
                    ->options(DashboardPeriod::monthOptions())
                    ->default(now()->month)
                    ->visible(fn (Get $get): bool => $get('preset') === 'month'),

                Select::make('range_year')
                    ->label('Año')
                    ->options(DashboardPeriod::yearOptions())
                    ->default(now()->year)
                    ->visible(fn (Get $get): bool => $get('preset') === 'range'),

                Select::make('range_from_month')
                    ->label('Desde')
                    ->options(DashboardPeriod::monthOptions())
                    ->default(1)
                    ->visible(fn (Get $get): bool => $get('preset') === 'range'),

                Select::make('range_to_month')
                    ->label('Hasta')
                    ->options(DashboardPeriod::monthOptions())
                    ->default(now()->month)
                    ->visible(fn (Get $get): bool => $get('preset') === 'range'),
            ]);
    }
}
