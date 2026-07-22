<?php

namespace App\Filament\Resources\EquipmentCategories;

use App\Filament\Resources\EquipmentCategories\Pages\CreateEquipmentCategory;
use App\Filament\Resources\EquipmentCategories\Pages\EditEquipmentCategory;
use App\Filament\Resources\EquipmentCategories\Pages\ListEquipmentCategories;
use App\Filament\Resources\EquipmentCategories\Pages\ViewEquipmentCategory;
use App\Filament\Resources\EquipmentCategories\Schemas\EquipmentCategoryForm;
use App\Filament\Resources\EquipmentCategories\Schemas\EquipmentCategoryInfolist;
use App\Filament\Resources\EquipmentCategories\Tables\EquipmentCategoriesTable;
use App\Models\EquipmentCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EquipmentCategoryResource extends Resource
{
    protected static ?string $model = EquipmentCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Categoría de Equipo';

    protected static ?string $pluralModelLabel = 'Categorías de Equipos';

    protected static string|UnitEnum|null $navigationGroup = 'Gestión de Activos';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        // Oculto para los roles de tenant; solo el superadministrador de plataforma lo ve.
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EquipmentCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentCategories::route('/'),
            'create' => CreateEquipmentCategory::route('/create'),
            'view' => ViewEquipmentCategory::route('/{record}'),
            'edit' => EditEquipmentCategory::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
