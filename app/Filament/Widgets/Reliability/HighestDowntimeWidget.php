<?php

namespace App\Filament\Widgets\Reliability;

use App\Models\EquipmentKpi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class HighestDowntimeWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Equipos con Mayor Tiempo de Parada — Top 10')
            ->query(
                EquipmentKpi::query()
                    ->with(['equipment', 'equipment.plant'])
                    ->whereNotNull('availability_percentage')
                    ->where('downtime_hours', '>', 0)
                    ->orderByDesc('downtime_hours')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('equipment.name')
                    ->label('Equipo')
                    ->searchable(),

                TextColumn::make('equipment.plant.name')
                    ->label('Planta')
                    ->placeholder('—'),

                TextColumn::make('downtime_hours')
                    ->label('Horas de parada')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' h')
                    ->sortable(),

                TextColumn::make('failure_count')
                    ->label('Nº de fallas'),

                TextColumn::make('availability_percentage')
                    ->label('Disponibilidad')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).'%'),
            ])
            ->paginated(false);
    }
}
