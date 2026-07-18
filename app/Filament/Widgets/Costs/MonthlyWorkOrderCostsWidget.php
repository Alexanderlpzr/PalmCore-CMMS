<?php

namespace App\Filament\Widgets\Costs;

use App\Filament\Widgets\Costs\Concerns\ResolvesCostReportFilters;
use App\Models\WorkOrder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Las órdenes completadas del mes, de la más cara a la más barata: el detalle
 * detrás del total, para poder ir a la OT que se llevó el pedazo grande del
 * presupuesto.
 */
class MonthlyWorkOrderCostsWidget extends BaseWidget
{
    use InteractsWithPageFilters;
    use ResolvesCostReportFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Órdenes del mes')
            ->query($this->monthQuery())
            ->columns([
                TextColumn::make('work_order_number')
                    ->label('N° OT')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Título')
                    ->limit(40)
                    ->tooltip(fn (WorkOrder $record): ?string => $record->title),
                TextColumn::make('work_order_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state): ?string => $state?->label()),
                TextColumn::make('equipment.name')
                    ->label('Equipo')
                    ->placeholder('—'),
                TextColumn::make('completed_at')
                    ->label('Completada')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('actual_cost_labor')
                    ->label('Mano de obra')
                    ->money('COP')
                    ->placeholder('—'),
                TextColumn::make('actual_cost_parts')
                    ->label('Repuestos')
                    ->money('COP')
                    ->placeholder('—'),
                TextColumn::make('actual_cost_external')
                    ->label('Terceros')
                    ->money('COP')
                    ->placeholder('—'),
                TextColumn::make('actual_cost_total')
                    ->label('Total')
                    ->money('COP')
                    ->weight('bold')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('actual_cost_total', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<WorkOrder>
     */
    private function monthQuery(): Builder
    {
        [$year, $month] = $this->resolvePeriod();
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return WorkOrder::query()
            ->where('plant_id', $this->resolvePlantId())
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->with('equipment');
    }
}
