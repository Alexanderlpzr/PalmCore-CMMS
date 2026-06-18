<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WebhookSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->label('URL')
                    ->limit(50)
                    ->copyable()
                    ->searchable(),

                TextColumn::make('events')
                    ->label('Eventos')
                    // Avoid strict array type hint: Filament v5 calls formatStateUsing once per
                    // array element (not the whole array), so $state is a single event string.
                    // json_decode fails on non-JSON strings, so fall back to [$state] to display it.
                    ->formatStateUsing(fn ($state): string => implode(', ', is_array($state) ? $state : (json_decode($state, true) ?? [$state])))
                    ->wrap()
                    ->limit(60),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('failure_count')
                    ->label('Fallos')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state >= 3 ? 'danger' : ($state > 0 ? 'warning' : 'gray')),

                TextColumn::make('last_triggered_at')
                    ->label('Último envío')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
