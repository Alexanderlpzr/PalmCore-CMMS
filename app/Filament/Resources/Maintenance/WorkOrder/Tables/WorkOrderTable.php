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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->getStateUsing(function (WorkOrder $record): ?string {
                        if ($record->status === WorkOrderStatus::Draft && $record->technicians_count === 0) {
                            return '⚠ Falta técnico';
                        }

                        if ($record->status->isPendingVerification()) {
                            return '🕓 Pend. verificación';
                        }

                        return null;
                    })
                    ->color(fn (WorkOrder $record): string => $record->status->isPendingVerification() ? 'warning' : 'danger')
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
                    ->getStateUsing(fn (WorkOrder $record): ?string => format_hours_minutes($record->actualHours()))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('actual_cost_total')
                    ->label('Costo total')
                    ->money('COP')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cost_variance')
                    ->label('Desviación')
                    ->badge()
                    ->placeholder('—')
                    ->state(function (WorkOrder $record): ?string {
                        $variance = $record->costVariance();

                        if ($variance === null) {
                            return null;
                        }

                        $pct = $record->costVariancePercentage();

                        return $pct !== null
                            ? ($pct > 0 ? '+' : '').$pct.'%'
                            : ($variance > 0 ? '+' : '−').'$'.number_format(abs($variance), 0, ',', '.');
                    })
                    ->color(fn (WorkOrder $record): string => match (true) {
                        $record->costVariance() === null => 'gray',
                        $record->costVariance() > 0 => 'danger',
                        $record->costVariance() < 0 => 'success',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('assigned_to_me')
                    ->label('Asignadas a mí')
                    ->toggle()
                    ->default(fn (): bool => auth()->user()?->cannot('work-orders.plan') ?? false)
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'technicians',
                        fn (Builder $technicians) => $technicians->where('user_id', auth()->id())
                    )),
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
                ViewAction::make()
                    ->tooltip('Ver el detalle de esta OT'),
                EditAction::make()
                    ->tooltip('Editar los datos de esta OT')
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
