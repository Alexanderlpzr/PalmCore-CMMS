<?php

namespace App\Filament\Resources\MeterReadings\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeterReadingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('equipment.code')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reading_value')
                    ->label('Dial')
                    ->numeric(1)
                    ->sortable(),
                TextColumn::make('delta')
                    ->label('Consumo')
                    ->numeric(1)
                    ->badge()
                    ->color(fn (float $state): string => $state > 0 ? 'success' : 'gray'),
                // El número que manda: nunca retrocede, ni cuando cambian el dial.
                TextColumn::make('accumulated_value')
                    ->label('Acumulado')
                    ->numeric(1)
                    ->sortable(),
                IconColumn::make('is_reset')
                    ->label('Reset')
                    ->boolean()
                    ->tooltip('El dial se cambió: la lectura bajó, el acumulado siguió.'),
                TextColumn::make('recordedBy.name')
                    ->label('Registró')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('is_reset')
                    ->label('Solo cambios de dial')
                    ->query(fn (Builder $query): Builder => $query->where('is_reset', true)),
            ])
            ->defaultSort('recorded_at', 'desc');
    }
}
