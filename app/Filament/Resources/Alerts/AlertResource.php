<?php

namespace App\Filament\Resources\Alerts;

use App\Domain\Alerts\Enums\AlertStatus;
use App\Filament\Resources\Alerts\Alert\Pages\ListAlerts;
use App\Filament\Resources\Alerts\Alert\Pages\ViewAlert;
use App\Filament\Resources\Alerts\Alert\Schemas\AlertInfolist;
use App\Filament\Resources\Alerts\Alert\Tables\AlertTable;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\Alert;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $modelLabel = 'Alerta';

    protected static ?string $pluralModelLabel = 'Alertas';

    protected static string|UnitEnum|null $navigationGroup = 'Centro de Alertas';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function infolist(Schema $schema): Schema
    {
        return AlertInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlertTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlerts::route('/'),
            'view' => ViewAlert::route('/{record}'),
        ];
    }

    /** Navega al recurso Filament correspondiente a la entidad de la alerta. */
    public static function getEntityUrl(Alert $alert): ?string
    {
        if ($alert->entity_type === null || $alert->entity_id === null) {
            return null;
        }

        return match ($alert->entity_type) {
            'maintenance_plan' => MaintenancePlanResource::getUrl('view', ['record' => $alert->entity_id]),
            'work_order' => WorkOrderResource::getUrl('view', ['record' => $alert->entity_id]),
            'spare_part' => SparePartResource::getUrl('view', ['record' => $alert->entity_id]),
            'equipment' => EquipmentResource::getUrl('view', ['record' => $alert->entity_id]),
            default => null,
        };
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::withoutGlobalScopes()
            ->where('status', AlertStatus::Open->value)
            ->where('severity', 'critical')
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }
}
