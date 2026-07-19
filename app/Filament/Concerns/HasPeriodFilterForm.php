<?php

namespace App\Filament\Concerns;

use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * El selector de período (últimos 12 meses / año completo / un mes / rango de
 * meses) que primero existió en el Dashboard de Indicadores. Se extrae aquí
 * para que cualquier página basada en Filament\Pages\Dashboard + HasFiltersForm
 * pueda ofrecer el mismo filtro sin repetir sus seis campos.
 */
trait HasPeriodFilterForm
{
    public function periodFilterForm(Schema $schema): Schema
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
