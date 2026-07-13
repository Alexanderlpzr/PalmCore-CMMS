<?php

namespace App\Filament\Resources\Maintenance\WorkOrder;

use App\Filament\Resources\Maintenance\WorkOrder\Pages\CreateWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\EditWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\ContractorsRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\PartsRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\PermitsRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\SignaturesRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\TechniciansRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\TimeLogsRelationManager;
use App\Filament\Resources\Maintenance\WorkOrder\Schemas\WorkOrderForm;
use App\Filament\Resources\Maintenance\WorkOrder\Schemas\WorkOrderInfolist;
use App\Filament\Resources\Maintenance\WorkOrder\Tables\WorkOrderTable;
use App\Models\WorkOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'Orden de Trabajo';

    protected static ?string $pluralModelLabel = 'Órdenes de Trabajo';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return WorkOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WorkOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkOrderTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'technicians' => TechniciansRelationManager::class,
            'contractors' => ContractorsRelationManager::class,
            'permits' => PermitsRelationManager::class,
            'timeLogs' => TimeLogsRelationManager::class,
            'parts' => PartsRelationManager::class,
            'comments' => CommentsRelationManager::class,
            'attachments' => AttachmentsRelationManager::class,
            'signatures' => SignaturesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['equipment'])->withCount('technicians');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkOrders::route('/'),
            'create' => CreateWorkOrder::route('/create'),
            'view' => ViewWorkOrder::route('/{record}'),
            'edit' => EditWorkOrder::route('/{record}/edit'),
        ];
    }
}
