<?php

namespace App\Filament\Resources\Permissions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PermissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Permiso')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('module')
                    ->label('Módulo')
                    ->badge()
                    ->state(fn ($record): string => explode('.', $record->name)[0] ?? '')
                    ->color('primary'),
                TextColumn::make('action')
                    ->label('Acción')
                    ->state(fn ($record): string => explode('.', $record->name)[1] ?? '')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('module')
                    ->label('Módulo')
                    ->options(fn (): array => \App\Models\Permission::orderBy('name')
                        ->pluck('name')
                        ->mapWithKeys(fn ($name) => [
                            explode('.', $name)[0] => ucfirst(explode('.', $name)[0]),
                        ])
                        ->unique()
                        ->toArray()
                    )
                    ->query(fn ($query, array $data) => filled($data['value'])
                        ? $query->where('name', 'like', $data['value'] . '.%')
                        : $query
                    ),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('name');
    }
}
