<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Tables;

use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Models\MaintenanceRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenanceRequestTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('request_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('equipment.code')
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('request_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (MaintenanceRequestType $state): string => $state->color())
                    ->formatStateUsing(fn (MaintenanceRequestType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (MaintenanceRequestPriority $state): string => $state->color())
                    ->formatStateUsing(fn (MaintenanceRequestPriority $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (MaintenanceRequestStatus $state): string => $state->color())
                    ->formatStateUsing(fn (MaintenanceRequestStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Creado por')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('requested_due_date')
                    ->label('Fecha límite')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('request_type')
                    ->label('Tipo')
                    ->options(MaintenanceRequestType::options()),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options(MaintenanceRequestPriority::options()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(MaintenanceRequestStatus::options()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->tooltip('Ver el detalle de esta solicitud'),
                EditAction::make()
                    ->tooltip('Editar los datos de esta solicitud')
                    ->visible(fn (MaintenanceRequest $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
