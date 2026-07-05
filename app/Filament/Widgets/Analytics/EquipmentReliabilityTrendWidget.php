<?php

namespace App\Filament\Widgets\Analytics;

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Models\Equipment;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

class EquipmentReliabilityTrendWidget extends ChartWidget
{
    use HasFiltersSchema;

    public ?Equipment $record = null;

    protected ?string $heading = 'MTBF / MTTR del equipo';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public function mount(?Equipment $record = null): void
    {
        $this->record = $record;

        parent::mount();
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Select::make('preset')
                    ->label('Periodo')
                    ->options([
                        'month' => 'Este mes',
                        'year' => 'Año completo',
                        'range' => 'Rango de meses',
                        DashboardPeriod::DEFAULT_PRESET => 'Últimos 12 meses',
                    ])
                    ->default('month')
                    ->live()
                    ->selectablePlaceholder(false),

                Select::make('year')
                    ->label('Año')
                    ->options(DashboardPeriod::yearOptions())
                    ->default(now()->year)
                    ->visible(fn (Get $get): bool => $get('preset') === 'year'),

                Select::make('month')
                    ->label('Mes')
                    ->options(DashboardPeriod::monthOptions())
                    ->default(now()->month)
                    ->visible(fn (Get $get): bool => $get('preset') === 'month'),

                Select::make('range_year')
                    ->label('Año')
                    ->options(DashboardPeriod::yearOptions())
                    ->default(now()->year)
                    ->visible(fn (Get $get): bool => $get('preset') === 'range'),

                Select::make('range_from_month')
                    ->label('Desde')
                    ->options(DashboardPeriod::monthOptions())
                    ->default(1)
                    ->visible(fn (Get $get): bool => $get('preset') === 'range'),

                Select::make('range_to_month')
                    ->label('Hasta')
                    ->options(DashboardPeriod::monthOptions())
                    ->default(now()->month)
                    ->visible(fn (Get $get): bool => $get('preset') === 'range'),
            ]);
    }

    public function getDescription(): ?string
    {
        $period = DashboardPeriod::label($this->filters);

        return "Tiempo Medio Entre Fallas y de Reparación (horas) — {$period}. Gaps indican meses sin fallas.";
    }

    protected function getData(): array
    {
        [$from, $to] = DashboardPeriod::resolve($this->filters);
        $service = app(AnalyticsService::class);

        $mtbf = $service->mtbfTrend($this->record->tenant_id, $from, $to, $this->record->id);
        $mttr = $service->mttrTrend($this->record->tenant_id, $from, $to, $this->record->id);

        return [
            'datasets' => [
                [
                    'label' => 'MTBF (h)',
                    'data' => array_map(fn ($p) => $p->value, $mtbf),
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(59, 130, 246, 1)',
                    'spanGaps' => false,
                ],
                [
                    'label' => 'MTTR (h)',
                    'data' => array_map(fn ($p) => $p->value, $mttr),
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.15)',
                    'tension' => 0.3,
                    'fill' => true,
                    'pointBackgroundColor' => 'rgba(168, 85, 247, 1)',
                    'spanGaps' => false,
                ],
            ],
            'labels' => array_map(fn ($p) => $p->label, $mtbf),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
