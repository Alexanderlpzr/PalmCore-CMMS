<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Tables;

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Filament\Resources\Maintenance\MaintenancePlan\Actions\RegisterManualExecutionAction;
use App\Models\MaintenancePlan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenancePlanTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // La columna «Faltan» consulta el equipo de cada plan; sin esto sería
            // una consulta extra por fila.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['equipment', 'schedule']))
            ->columns([
                TextColumn::make('plan_number')
                    ->label('Nº Plan')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('equipment.code')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equipmentComponent.name')
                    ->label('Pieza')
                    ->placeholder('Todo el equipo')
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('trigger_source')
                    ->label('Disparador')
                    ->badge()
                    ->color(fn (MaintenanceTriggerSource $state): string => $state->color())
                    ->formatStateUsing(fn (MaintenanceTriggerSource $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('time_frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->formatStateUsing(fn (?MaintenanceTimeFrequency $state): ?string => $state?->label())
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('meter_interval')
                    ->label('Horómetro')
                    ->suffix(' h')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('hours_remaining')
                    ->label('Faltan')
                    ->badge()
                    ->getStateUsing(fn (MaintenancePlan $record): ?string => app(EquipmentMeterReadingService::class)->remainingLabel($record))
                    ->color(fn (MaintenancePlan $record): string => app(EquipmentMeterReadingService::class)->remainingColor($record))
                    ->tooltip('Horas de horómetro que faltan para el vencimiento')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('schedule.next_due_at')
                    ->label('Próx. vencimiento')
                    ->date('d/m/Y')
                    ->placeholder('Sin programar')
                    ->sortable(),
                TextColumn::make('schedule.times_executed')
                    ->label('Ej.')
                    ->placeholder('0')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('responsibleUser.name')
                    ->label('Responsable')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('trigger_source')
                    ->label('Disparador')
                    ->options(MaintenanceTriggerSource::options()),
                SelectFilter::make('time_frequency')
                    ->label('Frecuencia')
                    ->options(MaintenanceTimeFrequency::options()),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                RegisterManualExecutionAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
