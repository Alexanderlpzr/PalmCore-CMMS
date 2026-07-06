<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    protected static ?string $title = 'Hoja de Vida (Órdenes de Trabajo)';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->workOrders()->count();

        return $count > 0 ? (string) $count : null;
    }

    // Read-only history — work orders are created from a Solicitud de
    // Mantenimiento, not directly from the equipment's own page.
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('work_order_number')
            ->columns([
                TextColumn::make('work_order_number')
                    ->label('OT')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('title')
                    ->label('Título')
                    ->limit(35),
                TextColumn::make('work_order_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (WorkOrderType $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderType $state): string => $state->label()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (WorkOrderStatus $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('technicians')
                    ->label('Técnico(s)')
                    ->getStateUsing(fn (WorkOrder $record): string => $record->technicians
                        ->map(fn ($tech) => $tech->user?->name)
                        ->filter()
                        ->implode(', ') ?: '—'),
                TextColumn::make('completed_at')
                    ->label('Completada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('actual_labor_hours')
                    ->label('Horas reales')
                    ->getStateUsing(fn (WorkOrder $record): ?string => format_hours_minutes($record->actualHours()))
                    ->placeholder('—'),
                TextColumn::make('actual_cost_total')
                    ->label('Costo total')
                    ->money('COP')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('work_order_type')
                    ->label('Tipo')
                    ->options(WorkOrderType::options()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(WorkOrderStatus::options()),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(WorkOrderPriority::options()),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Ver')
                    ->tooltip('Abrir el detalle completo de esta OT')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (WorkOrder $record): string => WorkOrderResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
