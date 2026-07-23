<?php

namespace App\Filament\Resources\Equipment;

use App\Filament\Resources\Equipment\Pages\CreateEquipment;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\Equipment\RelationManagers\ComponentsRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\FailureModeAnalysisRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\MaintenancePlansRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\PhotosRelationManager;
use App\Filament\Resources\Equipment\RelationManagers\WorkOrdersRelationManager;
use App\Filament\Resources\Equipment\Schemas\EquipmentForm;
use App\Filament\Resources\Equipment\Schemas\EquipmentInfolist;
use App\Filament\Resources\Equipment\Tables\EquipmentTable;
use App\Models\Equipment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EquipmentResource extends Resource
{
    protected static ?string $model = Equipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'Equipo';

    protected static ?string $pluralModelLabel = 'Equipos';

    protected static string|UnitEnum|null $navigationGroup = 'Gestión de Activos';

    protected static ?int $navigationSort = 4;

    protected static bool $isScopedToTenant = true;

    public static function shouldRegisterNavigation(): bool
    {
        // Equipos SÍ lo ve el admin de tenant (necesita el catálogo de máquinas para
        // el día a día); el resto de Gestión de Activos sigue oculto. Se muestra a
        // quien pueda ver equipos, no solo al superadministrador.
        return (bool) (auth()->user()?->is_super_admin || auth()->user()?->can('equipment.view'));
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EquipmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'components' => ComponentsRelationManager::class,
            'maintenance_plans' => MaintenancePlansRelationManager::class,
            'failure_mode_analyses' => FailureModeAnalysisRelationManager::class,
            'documents' => DocumentsRelationManager::class,
            'photos' => PhotosRelationManager::class,
            'work_orders' => WorkOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipment::route('/'),
            'create' => CreateEquipment::route('/create'),
            'view' => ViewEquipment::route('/{record}'),
            'edit' => EditEquipment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['category', 'plant', 'area', 'manufacturer']);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['kpi', 'ongoingDowntimeEvent']);
    }
}
