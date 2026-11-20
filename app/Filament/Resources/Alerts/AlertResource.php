<?php

namespace App\Filament\Resources\Alerts;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertStatus;
use App\Filament\Concerns\RequiresMaintenanceAccess;
use App\Filament\Resources\Alerts\Alert\Pages\ListAlerts;
use App\Filament\Resources\Alerts\Alert\Pages\ViewAlert;
use App\Filament\Resources\Alerts\Alert\Schemas\AlertInfolist;
use App\Filament\Resources\Alerts\Alert\Tables\AlertTable;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Models\Alert;
use App\Models\EquipmentIssueReport;
use App\Models\SstFindingTask;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AlertResource extends Resource
{
    use RequiresMaintenanceAccess;

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
            // Al reporte de novedad, donde mantenimiento lo vuelve OT; quien no ve los
            // reportes pero sí los hallazgos (SST, Ambiental), al hallazgo.
            EquipmentIssueReport::class => match (true) {
                IssueReportResource::canViewAny() => IssueReportResource::getUrl('view', ['record' => $alert->entity_id]),
                SstFindingResource::canViewAny() && ($task = SstFindingTask::query()->where('issue_report_id', $alert->entity_id)->first()) !== null => SstFindingResource::getUrl('view', ['record' => $task->sst_finding_id]),
                default => null,
            },
            default => null,
        };
    }

    /**
     * Dos entradas en el menú: «Alertas» —las de mantenimiento, inventario, OT…— y «SST y
     * Ambiental», las de los hallazgos que esperan a mantenimiento. Cada una con su número
     * (pedido 2026-11-19); las dos abren la misma lista, en su pestaña.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $isList = fn (): bool => request()->routeIs(static::getRouteBaseName().'.*');

        return [
            ...array_map(
                fn (NavigationItem $item): NavigationItem => $item->isActiveWhen(fn (): bool => $isList() && request()->query('tab') !== ListAlerts::SAFETY_TAB),
                parent::getNavigationItems(),
            ),
            NavigationItem::make('SST y Ambiental')
                ->group(static::getNavigationGroup())
                ->icon(Heroicon::OutlinedShieldExclamation)
                ->sort(static::getNavigationSort() + 1)
                ->badge(self::openCount(safety: true), 'warning')
                ->url(fn (): string => static::getUrl('index', ['tab' => ListAlerts::SAFETY_TAB]))
                ->isActiveWhen(fn (): bool => $isList() && request()->query('tab') === ListAlerts::SAFETY_TAB),
        ];
    }

    /** Las críticas abiertas de mantenimiento, sin las de los hallazgos. */
    public static function getNavigationBadge(): ?string
    {
        return self::openCount(safety: false, onlyCritical: true);
    }

    /**
     * Las alertas abiertas de la empresa que se está mirando. Antes contaba las de todas
     * las empresas de la plataforma (2026-11-19).
     */
    private static function openCount(bool $safety, bool $onlyCritical = false): ?string
    {
        $tenantId = Filament::getTenant()?->getKey();

        if ($tenantId === null) {
            return null;
        }

        $count = Alert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', AlertStatus::Open->value)
            ->when($onlyCritical, fn (Builder $query) => $query->where('severity', 'critical'))
            ->where('category', $safety ? '=' : '!=', AlertCategory::Safety->value)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }
}
