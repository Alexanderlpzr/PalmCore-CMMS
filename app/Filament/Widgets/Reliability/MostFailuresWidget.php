<?php

namespace App\Filament\Widgets\Reliability;

use App\Models\EquipmentKpi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MostFailuresWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Equipos con Más Fallas — Top 10')
            ->query(
                EquipmentKpi::query()
                    ->with(['equipment', 'equipment.plant'])
                    ->whereNotNull('availability_percentage')
                    ->where('failure_count', '>', 0)
                    ->orderByDesc('failure_count')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('equipment.name')
                    ->label('Equipo')
                    ->searchable(),

                TextColumn::make('equipment.plant.name')
                    ->label('Planta')
                    ->placeholder('—'),

                TextColumn::make('failure_count')
                    ->label('Nº de fallas')
                    ->sortable(),

                TextColumn::make('mttr_hours')
                    ->label('MTTR')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? number_format((float) $state, 2).' h'
                        : 'Sin fallas registradas'
                    )
                    ->placeholder('Sin fallas registradas'),

                TextColumn::make('downtime_hours')
                    ->label('Horas de parada')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' h'),
            ])
            ->paginated(false);
    }
}
