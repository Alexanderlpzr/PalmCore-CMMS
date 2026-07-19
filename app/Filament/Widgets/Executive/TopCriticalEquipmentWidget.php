<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class TopCriticalEquipmentWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Equipos Críticos — Top 10 por Fallas')
            ->records(function (): array {
                [$from, $to] = DashboardPeriod::resolve($this->pageFilters);

                return collect(
                    app(ExecutiveDashboardService::class)->topEquipment(Filament::getTenant()->id, $from, $to)
                )->keyBy('id')->all();
            })
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
                    ->label(fn (): string => 'Costo — '.DashboardPeriod::labelForSnapshot($this->pageFilters))
                    ->formatStateUsing(fn ($state): string => 'COP '.number_format((float) $state, 0, ',', '.')),
            ])
            ->paginated(false);
    }
}
