<?php

namespace App\Filament\Resources\Inventory\Warehouse\RelationManagers;

use App\Domain\Inventory\Enums\InventoryTransactionType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Movimientos';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->transactions()->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_number')
            ->columns([
                TextColumn::make('transaction_number')
                    ->label('N° Movimiento')
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (InventoryTransactionType $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('spare_part_code_snapshot')
                    ->label('Código repuesto')
                    ->searchable(),
                TextColumn::make('spare_part_name_snapshot')
                    ->label('Repuesto')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label('Costo unit.')
                    ->money('COP'),
                TextColumn::make('total_cost')
                    ->label('Total')
                    ->money('COP')
                    ->placeholder('—'),
                TextColumn::make('new_stock')
                    ->label('Stock resultante')
                    ->numeric(decimalPlaces: 4),
                TextColumn::make('performedBy.name')
                    ->label('Realizado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('performed_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(InventoryTransactionType::options()),
            ])
            ->defaultSort('performed_at', 'desc');
    }
}
