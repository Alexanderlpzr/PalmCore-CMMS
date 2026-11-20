<?php

use App\Domain\Alerts\Data\CreateAlertData;
use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Services\AlertService;
use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Safety\Services\SafetyFindingService;
use App\Filament\Resources\Alerts\Alert\Pages\ListAlerts;
use App\Filament\Resources\Alerts\AlertResource;
use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Filament\Widgets\Lists\AlertKpis;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Alert;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentIssueReport;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Las alertas de los hallazgos de SST y Ambiental van aparte de las de mantenimiento:
 * su categoría, su entrada en el menú con su número y su pestaña (2026-11-19).
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $persona = function (string $rol): User {
        $user = User::factory()->create(['is_active' => true]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->sst = $persona('sst');
    $this->mantenimiento = $persona('mantenimiento');

    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $plant->id, 'name' => 'Esterilización', 'code' => 'EST-01']);
    $this->equipo = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $plant->id, 'area_id' => $this->area->id, 'name' => 'Prensa 1']);
});

it('files the alerts of the findings apart from the maintenance ones, with their own menu entry and tab', function (): void {
    $hallazgo = app(SafetyFindingService::class)->create($this->tenant->id, [
        'reported_on' => now()->toDateString(), 'description' => 'Escalón doblado', 'area_id' => $this->area->id,
        'priority' => 'alta', 'areas' => ['mantenimiento'],
    ], $this->sst);
    EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $this->equipo->id, 'status' => 'open',
        'severity' => IssueSeverity::Critical->value, 'description' => 'Fuga de aceite',
    ]);

    $deHallazgo = Alert::query()->where('category', AlertCategory::Safety->value)->sole();
    $deMantenimiento = Alert::query()->where('category', AlertCategory::Maintenance->value)->sole();

    expect($deHallazgo->title)->toBe('Hallazgo SST pendiente en mantenimiento: Infraestructura — Esterilización')
        ->and($deMantenimiento->title)->toBe('Reporte de novedad pendiente: Prensa 1');

    // Una alerta crítica de otra empresa no cuenta.
    $otra = Tenant::factory()->create();
    app(AlertService::class)->create(new CreateAlertData(tenantId: $otra->id, severity: AlertSeverity::Critical, category: AlertCategory::Maintenance, title: 'De otra empresa', message: 'x'));

    $this->actingAs($this->mantenimiento);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $menu = collect(AlertResource::getNavigationItems())->mapWithKeys(fn (NavigationItem $item): array => [$item->getLabel() => $item->getBadge()]);
    expect($menu->all())->toBe(['Alertas' => '1', 'SST y Ambiental' => '1']);

    Livewire::test(ListAlerts::class)
        ->assertCanSeeTableRecords([$deMantenimiento])
        ->assertCanNotSeeTableRecords([$deHallazgo])
        ->set('activeTab', ListAlerts::SAFETY_TAB)
        ->assertCanSeeTableRecords([$deHallazgo])
        ->assertCanNotSeeTableRecords([$deMantenimiento]);

    // Mantenimiento abre el reporte de novedad, donde lo vuelve OT.
    $reporte = $hallazgo->tasks()->sole()->issue_report_id;
    expect(AlertResource::getEntityUrl($deHallazgo))->toBe(IssueReportResource::getUrl('view', ['record' => $reporte]));
});

it('moves the alerts the findings had already opened to their own category', function (): void {
    $reporte = EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $this->equipo->id, 'status' => 'open',
        'reporter_position' => 'Gestión Ambiental',
    ]);
    $alerta = Alert::query()->where('entity_id', $reporte->id)->sole();
    DB::table('alerts')->where('id', $alerta->id)->update(['category' => 'maintenance', 'title' => 'Reporte de novedad pendiente: Prensa 1']);

    $migration = require database_path('migrations/2026_11_20_042810_reclassify_sst_finding_alerts.php');
    $migration->up();

    expect($alerta->fresh()->category)->toBe(AlertCategory::Safety)
        ->and($alerta->fresh()->title)->toBe('Hallazgo ambiental pendiente en mantenimiento: Prensa 1');
});

it('counts in the cards on top only the alerts of the tab being looked at', function (): void {
    app(SafetyFindingService::class)->create($this->tenant->id, [
        'reported_on' => now()->toDateString(), 'description' => 'Escalón doblado', 'area_id' => $this->area->id,
        'priority' => 'alta', 'areas' => ['mantenimiento'],
    ], $this->sst);
    EquipmentIssueReport::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $this->equipo->id, 'status' => 'open',
        'severity' => IssueSeverity::Medium->value, 'description' => 'Ruido en la prensa',
    ]);

    $this->actingAs($this->mantenimiento);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $cifras = function (?string $tab): array {
        $widget = new AlertKpis;
        $widget->activeTab = $tab;
        $kpis = collect((fn (): array => $this->kpis())->call($widget))->pluck('value', 'label');

        return [$kpis['Abiertas'], $kpis['Críticas abiertas']];
    };

    // El hallazgo es crítico; el reporte de mantenimiento, no.
    expect($cifras('mantenimiento'))->toBe(['1', '0'])
        ->and($cifras(null))->toBe(['1', '0'])
        ->and($cifras(ListAlerts::SAFETY_TAB))->toBe(['1', '1'])
        ->and($cifras('todas'))->toBe(['2', '1']);

    expect(Livewire::test(ListAlerts::class)->instance()->getWidgetData())->toBe(['activeTab' => 'mantenimiento']);
});
