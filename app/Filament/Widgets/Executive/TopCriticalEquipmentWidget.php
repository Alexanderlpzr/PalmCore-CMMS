<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopCriticalEquipmentWidget extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Equipos Críticos — Top 10 por Fallas')
            ->records(fn (): array => collect(
                app(ExecutiveDashboardService::class)->topEquipment(Filament::getTenant()->id)
            )->keyBy('id')->all())
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('—'),

                TextColumn::make('name')
                    ->label('Equipo')
                    ->placeholder('—'),

                TextColumn::make('area_name')
                    ->label('Área')
                    ->placeholder('—'),

                TextColumn::make('failure_count')
                    ->label('Fallas')
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('downtime_hours')
                    ->label('Horas de parada')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 1).' h'),

                TextColumn::make('monthly_cost')
                    ->label('Costo Mensual')
                    ->formatStateUsing(fn ($state): string => 'COP '.number_format((float) $state, 0, ',', '.')),
            ])
            ->paginated(false);
    }
}
