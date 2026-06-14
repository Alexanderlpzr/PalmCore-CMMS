<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan;

use App\Filament\Resources\Maintenance\MaintenancePlan\Pages\CreateMaintenancePlan;
use App\Filament\Resources\Maintenance\MaintenancePlan\Pages\EditMaintenancePlan;
use App\Filament\Resources\Maintenance\MaintenancePlan\Pages\ListMaintenancePlans;
use App\Filament\Resources\Maintenance\MaintenancePlan\Pages\ViewMaintenancePlan;
use App\Filament\Resources\Maintenance\MaintenancePlan\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Maintenance\MaintenancePlan\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Maintenance\MaintenancePlan\Schemas\MaintenancePlanForm;
use App\Filament\Resources\Maintenance\MaintenancePlan\Schemas\MaintenancePlanInfolist;
use App\Filament\Resources\Maintenance\MaintenancePlan\Tables\MaintenancePlanTable;
use App\Models\MaintenancePlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MaintenancePlanResource extends Resource
{
    protected static ?string $model = MaintenancePlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $modelLabel = 'Plan de Mantenimiento';

    protected static ?string $pluralModelLabel = 'Planes de Mantenimiento';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 4;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return MaintenancePlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaintenancePlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenancePlanTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'tasks' => TasksRelationManager::class,
            'attachments' => AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenancePlans::route('/'),
            'create' => CreateMaintenancePlan::route('/create'),
            'view' => ViewMaintenancePlan::route('/{record}'),
            'edit' => EditMaintenancePlan::route('/{record}/edit'),
        ];
    }
}
