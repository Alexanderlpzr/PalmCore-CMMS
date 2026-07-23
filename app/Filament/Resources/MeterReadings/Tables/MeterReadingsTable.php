<?php

namespace App\Filament\Resources\MeterReadings\Tables;

use App\Domain\Assets\Enums\MeterReadingFrequency;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MeterReadingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Agrupadas por equipo, la historia de cada dial se lee de corrido —
            // sin esto, las lecturas de equipos distintos se entrelazan por fecha
            // y hay que buscar entre todas para seguir una sola máquina.
            ->groups([
                Group::make('equipment.code')
                    ->label('Equipo')
                    ->collapsible(),
            ])
            ->defaultGroup('equipment.code')
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
                SelectFilter::make('reading_frequency')
                    ->label('Ronda')
                    ->options(MeterReadingFrequency::options())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['value'] ?? null,
                            fn (Builder $q, string $freq): Builder => $q->whereHas(
                                'equipment',
                                fn (Builder $e) => $e->where('reading_frequency', $freq)
                            )
                        )),
                SelectFilter::make('equipment_id')
                    ->label('Equipo')
                    ->relationship('equipment', 'code')
                    ->searchable(),
                Filter::make('is_reset')
                    ->label('Solo cambios de dial')
                    ->query(fn (Builder $query): Builder => $query->where('is_reset', true)),
            ])
            ->defaultSort('recorded_at', 'desc');
    }
}
