<?php

use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Filament\Resources\AttendanceDays\Pages\ListAttendanceDays;
use App\Filament\Resources\AttendanceScans\Pages\ListAttendanceScans;
use App\Filament\Resources\Downtime\Pages\ListDowntimeEvents;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\ListPurchaseOrders;
use App\Filament\Resources\Inventory\SparePart\Pages\ListSpareParts;
use App\Filament\Resources\Inventory\Warehouse\Pages\ListWarehouses;
use App\Filament\Resources\Maintenance\IssueReport\Pages\ListIssueReports;
use App\Filament\Resources\Maintenance\MaintenancePlan\Pages\ListMaintenancePlans;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\ListMaintenanceRequests;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Filament\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Resources\ProductionCalendar\Pages\ListProductionCalendarDays;
use App\Filament\Widgets\Lists\AttendanceDayKpis;
use App\Filament\Widgets\Lists\AttendanceScanKpis;
use App\Filament\Widgets\Lists\DowntimeKpis;
use App\Filament\Widgets\Lists\EmployeeKpis;
use App\Filament\Widgets\Lists\EquipmentKpis;
use App\Filament\Widgets\Lists\IssueReportKpis;
use App\Filament\Widgets\Lists\MaintenancePlanKpis;
use App\Filament\Widgets\Lists\MaintenanceRequestKpis;
use App\Filament\Widgets\Lists\MeterReadingKpis;
use App\Filament\Widgets\Lists\PayrollRunKpis;
use App\Filament\Widgets\Lists\ProductionKpis;
use App\Filament\Widgets\Lists\PurchaseOrderKpis;
use App\Filament\Widgets\Lists\SparePartKpis;
use App\Filament\Widgets\Lists\WarehouseKpis;
use App\Filament\Widgets\Lists\WorkOrderKpis;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * Las tarjetas encima de las listas de mantenimiento: cada lista las muestra, y las que
 * llevan filtro abren la misma lista con él puesto.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-16 10:00');

    $this->tenant = Tenant::factory()->create();
    CurrentTenant::set($this->tenant);
    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $area = Area::factory()->forPlant($plant)->create();
    $this->equipo = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $plant->id, 'area_id' => $area->id,
        'status' => EquipmentStatus::Active->value, 'criticality' => EquipmentCriticality::Critical->value,
    ]);

    $admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('shows its cards on top of every maintenance list', function (string $page, string $widget, string $label): void {
    Livewire::test($page)->assertOk();
    Livewire::test($widget)->assertOk()->assertSee($label);
})->with([
    'órdenes de trabajo' => [ListWorkOrders::class, WorkOrderKpis::class, 'Vencidas'],
    'solicitudes' => [ListMaintenanceRequests::class, MaintenanceRequestKpis::class, 'Por revisar'],
    'planes' => [ListMaintenancePlans::class, MaintenancePlanKpis::class, 'Próximos 7 días'],
    'reportes de novedad' => [ListIssueReports::class, IssueReportKpis::class, 'Sin atender'],
    'paradas' => [ListDowntimeEvents::class, DowntimeKpis::class, 'Horas paradas del mes'],
    'horómetros' => [ListMeterReadings::class, MeterReadingKpis::class, 'Leídos hoy'],
    'producción' => [ListProductionCalendarDays::class, ProductionKpis::class, 'Toneladas del mes'],
    'equipos' => [ListEquipment::class, EquipmentKpis::class, 'Sin plan preventivo'],
    'repuestos' => [ListSpareParts::class, SparePartKpis::class, 'Sin existencias'],
    'almacenes' => [ListWarehouses::class, WarehouseKpis::class, 'Valor del inventario'],
    'órdenes de compra' => [ListPurchaseOrders::class, PurchaseOrderKpis::class, 'Entrega vencida'],
    'nóminas' => [ListPayrollRuns::class, PayrollRunKpis::class, 'Última nómina cerrada'],
    'horas por confirmar' => [ListAttendanceDays::class, AttendanceDayKpis::class, 'Días por confirmar'],
    'marcas de portería' => [ListAttendanceScans::class, AttendanceScanKpis::class, 'Entradas sin salida'],
    'personal' => [ListEmployees::class, EmployeeKpis::class, 'Sin carné'],
]);

it('filters the purchase orders whose delivery date already passed', function (): void {
    $vencida = PurchaseOrder::factory()->create(['tenant_id' => $this->tenant->id, 'status' => PurchaseOrderStatus::Sent->value, 'expected_at' => now()->subDays(2)]);
    $atiempo = PurchaseOrder::factory()->create(['tenant_id' => $this->tenant->id, 'status' => PurchaseOrderStatus::Sent->value, 'expected_at' => now()->addDays(5)]);

    Livewire::test(ListPurchaseOrders::class)
        ->filterTable('vencidas', true)
        ->assertCanSeeTableRecords([$vencida])
        ->assertCanNotSeeTableRecords([$atiempo]);
});

it('counts the overdue work orders and the card filters the list to them', function (): void {
    $vencida = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'equipment_id' => $this->equipo->id, 'planned_end_at' => now()->subDays(3)]);
    $atiempo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'equipment_id' => $this->equipo->id, 'planned_end_at' => now()->addDays(3)]);

    Livewire::test(WorkOrderKpis::class)
        ->assertSee('Vencidas')
        ->assertSee('filters%5Bvencidas%5D%5BisActive%5D=1', escape: false);

    Livewire::test(ListWorkOrders::class)
        ->filterTable('vencidas', true)
        ->assertCanSeeTableRecords([$vencida])
        ->assertCanNotSeeTableRecords([$atiempo]);
});

it('filters the equipment without an active preventive plan', function (): void {
    Livewire::test(ListEquipment::class)
        ->filterTable('sin_plan', true)
        ->assertCanSeeTableRecords([$this->equipo]);
});
