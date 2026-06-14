<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest;

use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\CreateMaintenanceRequest;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\EditMaintenanceRequest;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\ListMaintenanceRequests;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\ViewMaintenanceRequest;
use App\Filament\Resources\Maintenance\MaintenanceRequest\RelationManagers\AttachmentsRelationManager;
use App\Filament\Resources\Maintenance\MaintenanceRequest\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Schemas\MaintenanceRequestForm;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Schemas\MaintenanceRequestInfolist;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Tables\MaintenanceRequestTable;
use App\Models\MaintenanceRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MaintenanceRequestResource extends Resource
{
    protected static ?string $model = MaintenanceRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Solicitud de Mantenimiento';

    protected static ?string $pluralModelLabel = 'Solicitudes de Mantenimiento';

    protected static string|UnitEnum|null $navigationGroup = 'Mantenimiento';

    protected static ?int $navigationSort = 2;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return MaintenanceRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MaintenanceRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaintenanceRequestTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'comments' => CommentsRelationManager::class,
            'attachments' => AttachmentsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['equipment', 'createdBy']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaintenanceRequests::route('/'),
            'create' => CreateMaintenanceRequest::route('/create'),
            'view' => ViewMaintenanceRequest::route('/{record}'),
            'edit' => EditMaintenanceRequest::route('/{record}/edit'),
        ];
    }
}
