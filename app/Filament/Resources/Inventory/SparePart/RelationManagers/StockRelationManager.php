<?php

namespace App\Filament\Resources\Inventory\SparePart\RelationManagers;

use App\Models\WarehouseSparePart;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StockRelationManager extends RelationManager
{
    protected static string $relationship = 'warehouseStock';

    protected static ?string $title = 'Stock por Almacén';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->warehouseStock()->where('current_stock', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('warehouse.name')
            ->columns([
                TextColumn::make('warehouse.code')
                    ->label('Cód. Almacén')
                    ->sortable(),
                TextColumn::make('warehouse.name')
                    ->label('Almacén')
                    ->sortable(),
                TextColumn::make('current_stock')
                    ->label('Stock actual')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('reserved_stock')
                    ->label('Reservado')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('available_stock')
                    ->label('Disponible')
                    ->getStateUsing(fn (WarehouseSparePart $record): float => $record->available_stock)
                    ->numeric(decimalPlaces: 4),
                TextColumn::make('average_unit_cost')
                    ->label('Costo prom.')
                    ->money('COP')
                    ->placeholder('—'),
                TextColumn::make('bin_location')
                    ->label('Ubicación')
                    ->placeholder('—'),
                TextColumn::make('last_counted_at')
                    ->label('Último conteo')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('warehouse.name');
    }
}
