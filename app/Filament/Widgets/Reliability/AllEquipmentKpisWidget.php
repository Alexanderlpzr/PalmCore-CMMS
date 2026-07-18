<?php

namespace App\Filament\Widgets\Reliability;

use App\Models\EquipmentKpi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Las tablas de arriba (peor disponibilidad, más fallas, mayor parada) solo
 * muestran un top 10 cada una — para ver un equipo que no cae en ninguno de
 * esos extremos, o para ordenar por cualquier columna a gusto, hacía falta el
 * listado completo. Esta es esa vista: todos los equipos, todas las columnas
 * ordenables, paginado en vez de cortado en diez.
 */
class AllEquipmentKpisWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Todos los Equipos')
            ->query(EquipmentKpi::query()->with(['equipment', 'equipment.plant']))
            ->columns([
                TextColumn::make('equipment.name')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('equipment.plant.name')
                    ->label('Planta')
                    ->placeholder('—'),

                TextColumn::make('availability_percentage')
                    ->label('Disponibilidad')
                    ->formatStateUsing(fn (?string $state): string => $state !== null
                        ? number_format((float) $state, 2).'%'
                        : '—'
                    )
                    ->sortable(),

                TextColumn::make('mtbf_hours')
                    ->label('MTBF')
                    ->formatStateUsing(fn (?string $state): string => $state !== null
                        ? number_format((float) $state, 2).' h'
                        : 'Sin fallas registradas'
                    )
                    ->sortable(),

                TextColumn::make('mttr_hours')
                    ->label('MTTR')
                    ->formatStateUsing(fn (?string $state): string => $state !== null
                        ? number_format((float) $state, 2).' h'
                        : 'Sin fallas registradas'
                    )
                    ->sortable(),

                TextColumn::make('failure_count')
                    ->label('Fallas')
                    ->sortable(),

                TextColumn::make('downtime_hours')
                    ->label('Horas de parada')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' h')
                    ->sortable(),
            ])
            ->defaultSort('availability_percentage')
            ->paginated([10, 25, 50, 100]);
    }
}
