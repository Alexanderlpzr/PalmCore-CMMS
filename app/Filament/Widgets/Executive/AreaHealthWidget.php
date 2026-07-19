<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use App\Domain\Analytics\Support\DashboardPeriod;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class AreaHealthWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Salud por Área')
            ->records(function (): array {
                [$from, $to] = DashboardPeriod::resolve($this->pageFilters);

                return collect(
                    app(ExecutiveDashboardService::class)->areas(Filament::getTenant()->id, $from, $to)
                )->keyBy('code')->all();
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Área'),

                TextColumn::make('availability')
                    ->label('Disponibilidad')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? number_format((float) $state, 1).'%' : 'Sin datos'),

                TextColumn::make('failure_count')
                    ->label('Fallas'),

                TextColumn::make('mttr_hours')
                    ->label('MTTR')
                    ->formatStateUsing(fn ($state): string => $state > 0 ? number_format((float) $state, 1).' h' : '—'),

                TextColumn::make('monthly_cost')
                    ->label(fn (): string => 'Costo — '.DashboardPeriod::labelForSnapshot($this->pageFilters))
                    ->formatStateUsing(fn ($state): string => 'COP '.number_format((float) $state, 0, ',', '.')),
            ])
            ->paginated(false);
    }
}
