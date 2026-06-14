<?php

use App\Domain\Reports\Enums\ExcelReportType;
use App\Domain\Reports\Excel\DowntimeExcelExport;
use App\Domain\Reports\Excel\ExcelReportManager;
use App\Domain\Reports\Excel\InventoryExcelExport;
use App\Domain\Reports\Excel\MaintenancePlanExcelExport;
use App\Domain\Reports\Excel\ReliabilityExcelExport;
use App\Domain\Reports\Excel\WorkOrderExcelExport;
use App\Jobs\GenerateDowntimeExcelJob;
use App\Jobs\GenerateInventoryExcelJob;
use App\Jobs\GenerateMaintenancePlansExcelJob;
use App\Jobs\GenerateReliabilityExcelJob;
use App\Jobs\GenerateWorkOrdersExcelJob;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use App\Models\MaintenancePlan;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// ── Helpers ───────────────────────────────────────────────────────────────────

function excelTenantWithUser(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    return ['tenant' => $tenant, 'user' => $user];
}

// ── InventoryExcelExport ──────────────────────────────────────────────────────

it('InventoryExcelExport rows() only returns parts for the requested tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    SparePart::factory()->create(['tenant_id' => $tenantA->id]);
    SparePart::factory()->create(['tenant_id' => $tenantB->id]);

    $rows = (new InventoryExcelExport($tenantA->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->tenant_id)->toBe($tenantA->id);
});

it('InventoryExcelExport rows() excludes soft-deleted parts', function () {
    $tenant = Tenant::factory()->create();
    $active = SparePart::factory()->create(['tenant_id' => $tenant->id]);
    $deleted = SparePart::factory()->create(['tenant_id' => $tenant->id]);
    $deleted->delete();

    $rows = (new InventoryExcelExport($tenant->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->id)->toBe($active->id);
});

it('InventoryExcelExport rows() returns empty collection when tenant has no parts', function () {
    $tenant = Tenant::factory()->create();

    $rows = (new InventoryExcelExport($tenant->id))->rows()->collect();

    expect($rows)->toBeEmpty();
});

it('InventoryExcelExport map() handles null relations without crashing', function () {
    $tenant = Tenant::factory()->create();
    $part = SparePart::factory()->create([
        'tenant_id' => $tenant->id,
        'manufacturer_id' => null,
        'supplier_id' => null,
    ]);
    $part->unsetRelations();

    $row = (new InventoryExcelExport($tenant->id))->map($part);

    expect($row)->toBeArray()
        ->and($row['Fabricante'])->toBeNull()
        ->and($row['Proveedor'])->toBeNull();
});

it('InventoryExcelExport store() writes an xlsx file to the reports disk', function () {
    Storage::fake('reports');

    $tenant = Tenant::factory()->create();
    SparePart::factory()->create(['tenant_id' => $tenant->id]);

    $path = $tenant->id.'/INV-test.xlsx';
    (new InventoryExcelExport($tenant->id))->store($path);

    expect(Storage::disk('reports')->exists($path))->toBeTrue();
});

// ── ReliabilityExcelExport ────────────────────────────────────────────────────

it('ReliabilityExcelExport rows() only returns KPIs for the requested tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);
    EquipmentKpi::factory()->create(['tenant_id' => $tenantA->id, 'equipment_id' => $equipA->id]);
    EquipmentKpi::factory()->create(['tenant_id' => $tenantB->id, 'equipment_id' => $equipB->id]);

    $rows = (new ReliabilityExcelExport($tenantA->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->tenant_id)->toBe($tenantA->id);
});

it('ReliabilityExcelExport rows() excludes soft-deleted KPI records', function () {
    $tenant = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $active = EquipmentKpi::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipA->id]);
    $deleted = EquipmentKpi::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipB->id]);
    $deleted->delete();

    $rows = (new ReliabilityExcelExport($tenant->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->id)->toBe($active->id);
});

it('ReliabilityExcelExport map() handles null KPI values without crashing', function () {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $kpi = EquipmentKpi::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'mtbf_hours' => null,
        'mttr_hours' => null,
        'availability_percentage' => null,
    ]);

    $row = (new ReliabilityExcelExport($tenant->id))->map($kpi);

    expect($row)->toBeArray()
        ->and($row['MTBF (h)'])->toBeNull()
        ->and($row['MTTR (h)'])->toBeNull()
        ->and($row['Disponibilidad (%)'])->toBeNull();
});

// ── WorkOrderExcelExport ──────────────────────────────────────────────────────

it('WorkOrderExcelExport rows() only returns work orders for the requested tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    WorkOrder::factory()->create(['tenant_id' => $tenantA->id]);
    WorkOrder::factory()->create(['tenant_id' => $tenantB->id]);

    $rows = (new WorkOrderExcelExport($tenantA->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->tenant_id)->toBe($tenantA->id);
});

it('WorkOrderExcelExport rows() excludes soft-deleted work orders', function () {
    $tenant = Tenant::factory()->create();
    $active = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);
    $deleted = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);
    $deleted->delete();

    $rows = (new WorkOrderExcelExport($tenant->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->id)->toBe($active->id);
});

// ── DowntimeExcelExport ───────────────────────────────────────────────────────

it('DowntimeExcelExport rows() only returns events for the requested tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);
    EquipmentDowntimeEvent::factory()->create(['tenant_id' => $tenantA->id, 'equipment_id' => $equipA->id]);
    EquipmentDowntimeEvent::factory()->create(['tenant_id' => $tenantB->id, 'equipment_id' => $equipB->id]);

    $rows = (new DowntimeExcelExport($tenantA->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->tenant_id)->toBe($tenantA->id);
});

it('DowntimeExcelExport map() handles null optional fields without crashing', function () {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $event = EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'ended_at' => null,
        'failure_mode' => null,
        'notes' => null,
    ]);

    $row = (new DowntimeExcelExport($tenant->id))->map($event);

    expect($row)->toBeArray()
        ->and($row['Fin'])->toBeNull()
        ->and($row['Modo de Falla'])->toBeNull()
        ->and($row['Notas'])->toBeNull();
});

// ── MaintenancePlanExcelExport ────────────────────────────────────────────────

it('MaintenancePlanExcelExport rows() only returns plans for the requested tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    MaintenancePlan::factory()->create(['tenant_id' => $tenantA->id]);
    MaintenancePlan::factory()->create(['tenant_id' => $tenantB->id]);

    $rows = (new MaintenancePlanExcelExport($tenantA->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->tenant_id)->toBe($tenantA->id);
});

it('MaintenancePlanExcelExport rows() excludes soft-deleted plans', function () {
    $tenant = Tenant::factory()->create();
    $active = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id]);
    $deleted = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id]);
    $deleted->delete();

    $rows = (new MaintenancePlanExcelExport($tenant->id))->rows()->collect();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->id)->toBe($active->id);
});

// ── Jobs ──────────────────────────────────────────────────────────────────────

it('GenerateInventoryExcelJob stores xlsx under tenant path and notifies user', function () {
    ['tenant' => $tenant, 'user' => $user] = excelTenantWithUser();
    Storage::fake('reports');

    (new GenerateInventoryExcelJob($tenant->id, $user->id))->handle();

    $files = Storage::disk('reports')->allFiles();
    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith($tenant->id.'/')
        ->and($files[0])->toEndWith('.xlsx');

    expect($user->notifications()->count())->toBeGreaterThan(0);
});

it('GenerateReliabilityExcelJob stores xlsx under tenant path and notifies user', function () {
    ['tenant' => $tenant, 'user' => $user] = excelTenantWithUser();
    Storage::fake('reports');

    (new GenerateReliabilityExcelJob($tenant->id, $user->id))->handle();

    $files = Storage::disk('reports')->allFiles();
    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith($tenant->id.'/')
        ->and($files[0])->toEndWith('.xlsx');

    expect($user->notifications()->count())->toBeGreaterThan(0);
});

it('GenerateWorkOrdersExcelJob stores xlsx under tenant path and notifies user', function () {
    ['tenant' => $tenant, 'user' => $user] = excelTenantWithUser();
    Storage::fake('reports');

    (new GenerateWorkOrdersExcelJob($tenant->id, $user->id))->handle();

    $files = Storage::disk('reports')->allFiles();
    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith($tenant->id.'/')
        ->and($files[0])->toEndWith('.xlsx');

    expect($user->notifications()->count())->toBeGreaterThan(0);
});

it('GenerateDowntimeExcelJob stores xlsx under tenant path and notifies user', function () {
    ['tenant' => $tenant, 'user' => $user] = excelTenantWithUser();
    Storage::fake('reports');

    (new GenerateDowntimeExcelJob($tenant->id, $user->id))->handle();

    $files = Storage::disk('reports')->allFiles();
    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith($tenant->id.'/')
        ->and($files[0])->toEndWith('.xlsx');

    expect($user->notifications()->count())->toBeGreaterThan(0);
});

it('GenerateMaintenancePlansExcelJob stores xlsx under tenant path and notifies user', function () {
    ['tenant' => $tenant, 'user' => $user] = excelTenantWithUser();
    Storage::fake('reports');

    (new GenerateMaintenancePlansExcelJob($tenant->id, $user->id))->handle();

    $files = Storage::disk('reports')->allFiles();
    expect($files)->toHaveCount(1)
        ->and($files[0])->toStartWith($tenant->id.'/')
        ->and($files[0])->toEndWith('.xlsx');

    expect($user->notifications()->count())->toBeGreaterThan(0);
});

// ── ExcelReportManager ────────────────────────────────────────────────────────

it('ExcelReportManager dispatches the correct job for each ExcelReportType', function () {
    Queue::fake();

    $tenantId = 'tenant-uuid';
    $userId = 'user-uuid';
    $manager = new ExcelReportManager;

    $manager->dispatch(ExcelReportType::Inventory, $tenantId, $userId);
    $manager->dispatch(ExcelReportType::Reliability, $tenantId, $userId);
    $manager->dispatch(ExcelReportType::WorkOrders, $tenantId, $userId);
    $manager->dispatch(ExcelReportType::DowntimeEvents, $tenantId, $userId);
    $manager->dispatch(ExcelReportType::MaintenancePlans, $tenantId, $userId);

    Queue::assertPushed(GenerateInventoryExcelJob::class);
    Queue::assertPushed(GenerateReliabilityExcelJob::class);
    Queue::assertPushed(GenerateWorkOrdersExcelJob::class);
    Queue::assertPushed(GenerateDowntimeExcelJob::class);
    Queue::assertPushed(GenerateMaintenancePlansExcelJob::class);
});

// ── ExcelReportType ───────────────────────────────────────────────────────────

it('ExcelReportType has correct labels in Spanish', function () {
    expect(ExcelReportType::Inventory->label())->toBe('Inventario')
        ->and(ExcelReportType::Reliability->label())->toBe('Confiabilidad')
        ->and(ExcelReportType::WorkOrders->label())->toBe('Órdenes de Trabajo')
        ->and(ExcelReportType::DowntimeEvents->label())->toBe('Eventos de Parada')
        ->and(ExcelReportType::MaintenancePlans->label())->toBe('Planes de Mantenimiento');
});

it('ExcelReportType filenames have correct prefixes and xlsx extension', function () {
    expect(ExcelReportType::Inventory->filename())->toStartWith('INV-')->toEndWith('.xlsx')
        ->and(ExcelReportType::Reliability->filename())->toStartWith('CONFIABILIDAD-')->toEndWith('.xlsx')
        ->and(ExcelReportType::WorkOrders->filename())->toStartWith('OT-')->toEndWith('.xlsx')
        ->and(ExcelReportType::DowntimeEvents->filename())->toStartWith('PARADAS-')->toEndWith('.xlsx')
        ->and(ExcelReportType::MaintenancePlans->filename())->toStartWith('PM-')->toEndWith('.xlsx');
});
