<?php

namespace App\Filament\Resources\Inventory\SparePart;

use App\Filament\Resources\Inventory\SparePart\Pages\CreateSparePart;
use App\Filament\Resources\Inventory\SparePart\Pages\EditSparePart;
use App\Filament\Resources\Inventory\SparePart\Pages\ListSpareParts;
use App\Filament\Resources\Inventory\SparePart\Pages\ViewSparePart;
use App\Filament\Resources\Inventory\SparePart\RelationManagers\StockRelationManager;
use App\Filament\Resources\Inventory\SparePart\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Inventory\SparePart\Schemas\SparePartForm;
use App\Filament\Resources\Inventory\SparePart\Schemas\SparePartInfolist;
use App\Filament\Resources\Inventory\SparePart\Tables\SparePartTable;
use App\Models\SparePart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SparePartResource extends Resource
{
    protected static ?string $model = SparePart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrench;

    protected static ?string $modelLabel = 'Repuesto';

    protected static ?string $pluralModelLabel = 'Repuestos';

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SparePartForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SparePartInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SparePartTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'warehouseStock' => StockRelationManager::class,
            'transactions' => TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpareParts::route('/'),
            'create' => CreateSparePart::route('/create'),
            'view' => ViewSparePart::route('/{record}'),
            'edit' => EditSparePart::route('/{record}/edit'),
        ];
    }
}
