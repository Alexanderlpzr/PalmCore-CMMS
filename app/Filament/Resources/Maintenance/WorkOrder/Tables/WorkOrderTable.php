<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Tables;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\WorkOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkOrderTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('work_order_number')
                    ->label('OT')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('equipment.code')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(35),
                TextColumn::make('work_order_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (WorkOrderType $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (WorkOrderPriority $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderPriority $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (WorkOrderStatus $state): string => $state->color())
                    ->formatStateUsing(fn (WorkOrderStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('needs_technician')
                    ->label('Alerta')
                    ->getStateUsing(fn (WorkOrder $record): ?string => ($record->status === WorkOrderStatus::Draft
                        && $record->technicians_count === 0)
                        ? '⚠ Falta técnico'
                        : null)
                    ->color('danger')
                    ->weight('bold'),
                IconColumn::make('equipment_stopped')
                    ->label('Equipo parado')
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
                TextColumn::make('planned_start_at')
                    ->label('Inicio planif.')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_labor_hours')
                    ->label('Horas reales')
                    ->formatStateUsing(fn (?float $state): ?string => format_hours_minutes($state))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_cost_total')
                    ->label('Costo total')
                    ->money('COP')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('work_order_type')
                    ->label('Tipo')
                    ->options(WorkOrderType::options()),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(WorkOrderPriority::options()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(WorkOrderStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (WorkOrder $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
