<?php

namespace App\Filament\Resources\PayrollParameters\Tables;

use App\Domain\HumanResources\Enums\PayrollParameter;
use App\Models\PayrollParameterVersion;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayrollParametersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('key')
                    ->label('Parámetro')
                    ->formatStateUsing(fn (string $state): string => PayrollParameter::tryFrom($state)?->label() ?? $state)
                    ->description(fn (PayrollParameterVersion $record): ?string => $record->parameter()?->group())
                    ->searchable()
                    ->sortable(),

                TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state, PayrollParameterVersion $record): string => $record->formattedValue())
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('effective_from')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                // Una vigencia sin cierre es la que está mandando hoy, y eso es lo que se
                // viene a mirar. Decir «Vigente» es más honesto que dejar la celda vacía.
                TextColumn::make('effective_to')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Vigente')
                    ->badge()
                    ->color(fn ($state): string => $state === null ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state): string => $state?->format('d/m/Y') ?? 'Vigente')
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Por qué cambia')
                    ->limitWithTooltip(60)
                    ->toggleable(),

                TextColumn::make('createdBy.name')
                    ->label('Cargado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('vigentes')
                    ->label('Solo lo que rige hoy')
                    ->query(fn (Builder $query): Builder => $query->whereNull('effective_to'))
                    ->default(),

                SelectFilter::make('key')
                    ->label('Parámetro')
                    ->options(PayrollParameter::options())
                    ->searchable(),
            ])
            ->recordActions([
                // Solo el tramo abierto se edita; la policy niega el resto.
                EditAction::make(),
            ]);
    }
}
