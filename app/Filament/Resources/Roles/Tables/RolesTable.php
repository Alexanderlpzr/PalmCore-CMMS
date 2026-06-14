<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tenant_name')
                    ->label('Empresa')
                    ->getStateUsing(fn ($record): string => Tenant::find($record->team_id)?->name ?? '—')
                    ->visible(fn (): bool => auth()->user()?->is_super_admin ?? false)
                    ->sortable(false),
                TextColumn::make('permissions_count')
                    ->label('Permisos')
                    ->counts('permissions')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
