<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Tables;

use App\Domain\Assets\Services\ReferenceDataService;
use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\PlantProcess;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\WorkOrder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                TextColumn::make('maintenance_area')
                    ->label('Clase de mantenimiento')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?MaintenanceArea $state): string => $state?->color() ?? 'gray')
                    ->formatStateUsing(fn (?MaintenanceArea $state): ?string => $state?->label())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('process')
                    ->label('Proceso')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?PlantProcess $state): ?string => $state?->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('executed_by')
                    ->label('Responsable(s)')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                // Hoja de vida del equipo: se elige la sección y el selector de
                // equipo queda reducido a los de esa sección. Con el equipo puesto,
                // el Histórico muestra todas sus OT y nada más.
                Filter::make('ubicacion')
                    ->label('Sección y equipo')
                    ->form([
                        Select::make('area_id')
                            ->label('Sección')
                            ->options(fn (): array => ReferenceDataService::allAreas(Filament::getTenant()?->id ?? ''))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('equipment_id', null)),
                        Select::make('equipment_id')
                            ->label('Equipo')
                            ->options(fn (Get $get): array => Equipment::query()
                                ->when($get('area_id'), fn (Builder $query, string $areaId) => $query->where('area_id', $areaId))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['area_id'] ?? null,
                            fn (Builder $query, string $areaId) => $query->whereHas(
                                'equipment',
                                fn (Builder $equipment) => $equipment->where('area_id', $areaId)
                            )
                        )
                        ->when(
                            $data['equipment_id'] ?? null,
                            fn (Builder $query, string $equipmentId) => $query->where('equipment_id', $equipmentId)
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['area_id'] ?? null) {
                            $indicators[] = 'Sección: '.(Area::withoutGlobalScopes()->find($data['area_id'])?->name ?? '—');
                        }

                        if ($data['equipment_id'] ?? null) {
                            $indicators[] = 'Equipo: '.(Equipment::withoutGlobalScopes()->find($data['equipment_id'])?->name ?? '—');
                        }

                        return $indicators;
                    }),
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
                SelectFilter::make('maintenance_area')
                    ->label('Clase de mantenimiento')
                    ->options(MaintenanceArea::options()),
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
