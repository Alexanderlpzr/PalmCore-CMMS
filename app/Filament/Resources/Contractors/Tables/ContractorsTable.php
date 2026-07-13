<?php

namespace App\Filament\Resources\Contractors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ContractorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Razón social')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('tax_id')
                    ->label('NIT')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('work_order_assignments_count')
                    ->label('OT ejecutadas')
                    ->counts('workOrderAssignments')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
