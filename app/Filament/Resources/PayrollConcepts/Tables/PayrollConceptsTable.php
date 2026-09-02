<?php

namespace App\Filament\Resources\PayrollConcepts\Tables;

use App\Domain\HumanResources\Enums\PayrollConceptType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollConceptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable(),
                TextColumn::make('name')->label('Concepto')->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (PayrollConceptType $state): string => $state->label())
                    ->color(fn (PayrollConceptType $state): string => $state->color()),
                IconColumn::make('counts_ibc_health')->label('IBC salud')->boolean(),
                IconColumn::make('counts_ibc_pension')->label('IBC pensión')->boolean(),
                IconColumn::make('counts_severance_base')->label('Prima y cesantías')->boolean(),
                IconColumn::make('counts_vacation_base')->label('Vacaciones')->boolean(),
                IconColumn::make('is_active')->label('Activo')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(PayrollConceptType::options()),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
