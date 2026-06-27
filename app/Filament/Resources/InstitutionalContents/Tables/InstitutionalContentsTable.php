<?php

namespace App\Filament\Resources\InstitutionalContents\Tables;

use App\Domain\Home\Enums\InstitutionalContentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class InstitutionalContentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_order')
                    ->label('Orden')
                    ->sortable()
                    ->width(60),
                ImageColumn::make('image_path')
                    ->label('Imagen')
                    ->disk('public')
                    ->width(60)
                    ->height(40),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (InstitutionalContentType $state) => $state->label())
                    ->sortable(),
                IconColumn::make('is_global')
                    ->label('Global')
                    ->boolean(),
                ToggleColumn::make('is_active')
                    ->label('Activo'),
                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(InstitutionalContentType::options()),
                TernaryFilter::make('is_global')
                    ->label('Global'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->reorderable('display_order')
            ->defaultSort('display_order');
    }
}
