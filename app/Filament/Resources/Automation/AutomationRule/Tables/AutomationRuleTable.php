<?php

namespace App\Filament\Resources\Automation\AutomationRule\Tables;

use App\Domain\Automation\Enums\AutomationMode;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AutomationRuleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ToggleColumn::make('is_active')
                    ->label('Activa')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Regla')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('event_type')
                    ->label('Evento')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),

                TextColumn::make('mode')
                    ->label('Modo')
                    ->badge()
                    ->color(fn (AutomationMode $state): string => $state->color())
                    ->formatStateUsing(fn (AutomationMode $state): string => $state->label()),

                TextColumn::make('last_executed_at')
                    ->label('Última ejecución')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('event_type');
    }
}
