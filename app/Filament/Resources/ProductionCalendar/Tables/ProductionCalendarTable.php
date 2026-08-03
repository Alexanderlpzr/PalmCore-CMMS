<?php

namespace App\Filament\Resources\ProductionCalendar\Tables;

use App\Models\Plant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionCalendarTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('calendar_date')
                    ->label('Fecha')
                    ->date('D d/m/Y')
                    ->sortable(),
                TextColumn::make('plant.name')
                    ->label('Planta')
                    ->sortable()
                    ->toggleable(),
                // Editable en línea: programar una jornada es escribir un número, no
                // abrir un formulario.
                TextInputColumn::make('programmed_hours')
                    ->label('Horas pagadas')
                    ->type('number')
                    ->rules(['numeric', 'min:0', 'max:24'])
                    ->summarize(Sum::make()->label('Total del periodo')),
                // La tonelada se captura junto a la hora, no en otra pantalla: el
                // día se cierra una vez, y separarlas garantizaba que una de las
                // dos se quedara sin llenar.
                TextInputColumn::make('processed_tons')
                    ->label('Fruta procesada (t)')
                    ->type('number')
                    ->rules(['numeric', 'min:0'])
                    ->summarize(Sum::make()->label('Total del periodo')),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limitWithTooltip(40)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('plant_id')
                    ->label('Planta')
                    ->options(fn (): array => Plant::orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('calendar_date', 'desc');
    }
}
