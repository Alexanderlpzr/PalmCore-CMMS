<?php

namespace App\Filament\Resources\Inventory\Warehouse\RelationManagers;

use App\Domain\Inventory\Enums\SparePartCategoryType;
use App\Models\WarehouseSparePart;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SparePartsRelationManager extends RelationManager
{
    protected static string $relationship = 'stock';

    protected static ?string $title = 'Repuestos en Stock';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->stock()->where('current_stock', '>', 0)->count();

        return $count > 0 ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sparePart.name')
            ->columns([
                TextColumn::make('sparePart.code')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('sparePart.name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable()
                    ->limit(35),
                TextColumn::make('sparePart.category_type')
                    ->label('Categoría')
                    ->badge()
                    ->color(fn (SparePartCategoryType $state): string => $state->color()),
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
            ])
            ->filters([
                SelectFilter::make('sparePart.category_type')
                    ->label('Categoría')
                    ->options(SparePartCategoryType::options())
                    ->relationship('sparePart', 'category_type'),
            ])
            ->defaultSort('sparePart.code');
    }
}
