<?php

namespace App\Filament\Resources\Inventory\Warehouse;

use App\Filament\Resources\Inventory\Warehouse\Pages\CreateWarehouse;
use App\Filament\Resources\Inventory\Warehouse\Pages\EditWarehouse;
use App\Filament\Resources\Inventory\Warehouse\Pages\ListWarehouses;
use App\Filament\Resources\Inventory\Warehouse\Pages\ViewWarehouse;
use App\Filament\Resources\Inventory\Warehouse\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\Inventory\Warehouse\RelationManagers\SparePartsRelationManager;
use App\Filament\Resources\Inventory\Warehouse\Schemas\WarehouseForm;
use App\Filament\Resources\Inventory\Warehouse\Schemas\WarehouseInfolist;
use App\Filament\Resources\Inventory\Warehouse\Tables\WarehouseTable;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'Almacén';

    protected static ?string $pluralModelLabel = 'Almacenes';

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WarehouseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehouseTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'stock' => SparePartsRelationManager::class,
            'transactions' => MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            'create' => CreateWarehouse::route('/create'),
            'view' => ViewWarehouse::route('/{record}'),
            'edit' => EditWarehouse::route('/{record}/edit'),
        ];
    }
}
