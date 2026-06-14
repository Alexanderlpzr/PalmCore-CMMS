<?php

namespace App\Filament\Widgets\Reliability;

use App\Models\EquipmentKpi;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WorstAvailabilityWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Equipos con Peor Disponibilidad — Top 10')
            ->query(
                EquipmentKpi::query()
                    ->with(['equipment', 'equipment.plant'])
                    ->whereNotNull('availability_percentage')
                    ->orderBy('availability_percentage')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('equipment.name')
                    ->label('Equipo')
                    ->searchable(),

                TextColumn::make('equipment.plant.name')
                    ->label('Planta')
                    ->placeholder('—'),

                TextColumn::make('availability_percentage')
                    ->label('Disponibilidad')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).'%')
                    ->sortable(),

                TextColumn::make('mtbf_hours')
                    ->label('MTBF')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? number_format((float) $state, 2).' h'
                        : 'Sin fallas registradas'
                    )
                    ->placeholder('Sin fallas registradas'),
            ])
            ->paginated(false);
    }
}
