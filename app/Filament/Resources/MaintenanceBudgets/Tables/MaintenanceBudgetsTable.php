<?php

namespace App\Filament\Resources\MaintenanceBudgets\Tables;

use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\PeriodFilter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class MaintenanceBudgetsTable
{
    private const MONTHS = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plant.name')
                    ->label('Planta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->label('Año')
                    ->sortable(),
                TextColumn::make('month')
                    ->label('Mes')
                    ->formatStateUsing(fn (int $state): string => self::MONTHS[$state] ?? (string) $state)
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Monto asignado')
                    ->money('COP')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Registró')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                TableExcelExport::action('Presupuesto de mantenimiento'),
            ])
            // El período a la vista, encima de la tabla, como en talento humano.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filters([
                PeriodFilter::yearMonth('year', 'month', 'Mes del presupuesto'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('year', 'desc');
    }
}
