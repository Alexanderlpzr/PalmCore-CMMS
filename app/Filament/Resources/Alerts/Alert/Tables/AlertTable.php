<?php

namespace App\Filament\Resources\Alerts\Alert\Tables;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AlertTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('severity')
                    ->label('Severidad')
                    ->badge()
                    ->sortable()
                    ->color(fn (AlertSeverity $state): string => $state->color())
                    ->formatStateUsing(fn (AlertSeverity $state): string => $state->label()),

                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->color(fn (AlertCategory $state): string => $state->color())
                    ->formatStateUsing(fn (AlertCategory $state): string => $state->label()),

                TextColumn::make('title')
                    ->label('Alerta')
                    ->searchable()
                    ->wrap()
                    ->weight('semibold'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable()
                    ->color(fn (AlertStatus $state): string => $state->color())
                    ->formatStateUsing(fn (AlertStatus $state): string => $state->label()),

                TextColumn::make('closedBy.name')
                    ->label('Cerrada por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('closed_at')
                    ->label('Cerrada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('severity')
                    ->label('Severidad')
                    ->options(AlertSeverity::class)
                    ->attribute('severity'),

                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(AlertCategory::class)
                    ->attribute('category'),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(AlertStatus::class)
                    ->attribute('status')
                    ->default(AlertStatus::Open->value),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->striped();
    }
}
