<?php

use App\Domain\Alerts\Enums\AlertStatus;
use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Reports\Excel\TableExcelExport;
use App\Filament\Filters\PayrollPeriodFilter;
use App\Filament\Pages\Despachos;
use App\Filament\Pages\Kardex;
use App\Filament\Pages\Notificaciones;
use App\Filament\Resources\Alerts\Alert\Pages\ListAlerts;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Audit\Pages\ListAuditLogs;
use App\Filament\Resources\Downtime\Pages\ListDowntimeEvents;
use App\Filament\Resources\Inventory\InventoryCount\Pages\ListInventoryCounts;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\ListPurchaseOrders;
use App\Filament\Resources\Inventory\SparePart\Pages\ListSpareParts;
use App\Filament\Resources\Maintenance\IssueReport\Pages\ListIssueReports;
use App\Filament\Resources\Maintenance\MaintenancePlan\Pages\ListMaintenancePlans;
use App\Filament\Resources\Maintenance\MaintenanceRequest\Pages\ListMaintenanceRequests;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Filament\Resources\MaintenanceBudgets\Pages\ListMaintenanceBudgets;
use App\Filament\Resources\ProductionCalendar\Pages\ListProductionCalendarDays;
use App\Filament\Resources\Safety\Pages\ListSstFindings;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentIssueReport;
use App\Models\InventoryCount;
use App\Models\InventoryTransaction;
use App\Models\MaintenanceBudget;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\ProductionCalendarDay;
use App\Models\PurchaseOrder;
use App\Models\SparePart;
use App\Models\SstFinding;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\Permission\PermissionRegistrar;

/*
 * Pedido el 2026-11-08: en todas las tablas se elige un mes, un año o un rango y se baja en
 * Excel lo filtrado. Segunda etapa, después de talento humano: mantenimiento, almacén, SST
 * y el resto de listas con fecha, con la barra «Periodo · Año · Mes» a la vista.
 *
 * Hoy es el 16 de noviembre de 2026. Cada tabla recibe un registro de noviembre y otro de
 * octubre; «noviembre» trae el primero y deja fuera el segundo.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $this->travelTo(Carbon::parse('2026-11-16 10:00'));

    // Superadministrador: es el único que ve la auditoría, y ve también las demás tablas.
    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->admin->assignRole('administrador-general');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

/** Un pedido de repuesto que espera en la cola de despachos, pedido en la fecha dada. */
function pedidoEnCola(Tenant $tenant, string $cuando): WorkOrderPart
{
    $orden = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => Equipment::factory()->create(['tenant_id' => $tenant->id])->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);
    $parte = SparePart::factory()->create(['tenant_id' => $tenant->id]);

    $pedido = WorkOrderPart::create([
        'tenant_id' => $tenant->id, 'work_order_id' => $orden->id, 'spare_part_id' => $parte->id,
        'warehouse_id' => Warehouse::factory()->create(['tenant_id' => $tenant->id])->id,
        'description' => $parte->name, 'quantity' => 1, 'unit_cost_snapshot' => 10_000,
        'status' => WorkOrderPartStatus::Requested->value, 'reserved_quantity' => 0, 'issued_quantity' => 0, 'returned_quantity' => 0,
    ]);
    $pedido->forceFill(['created_at' => Carbon::parse($cuando)])->saveQuietly();

    return $pedido;
}

/** Todo el texto del libro, una celda tras otra. */
function textoDelLibro(string $ruta): string
{
    $lector = new XlsxReader;
    $lector->open($ruta);
    $celdas = [];

    foreach ($lector->getSheetIterator() as $hoja) {
        foreach ($hoja->getRowIterator() as $fila) {
            $celdas = [...$celdas, ...$fila->toArray()];
        }
    }

    $lector->close();

    return implode(' | ', array_map(fn ($c): string => $c instanceof DateTimeInterface ? $c->format('d/m/Y') : (string) $c, $celdas));
}

dataset('tablas con fecha', [
    'órdenes de trabajo' => [ListWorkOrders::class, fn (Tenant $t, string $cuando): Model => WorkOrder::factory()->create(['tenant_id' => $t->id, 'planned_start_at' => $cuando])],
    'solicitudes' => [ListMaintenanceRequests::class, fn (Tenant $t, string $cuando): Model => MaintenanceRequest::factory()->create(['tenant_id' => $t->id, 'created_at' => $cuando])],
    'planes' => [ListMaintenancePlans::class, fn (Tenant $t, string $cuando): Model => MaintenancePlan::factory()->create(['tenant_id' => $t->id, 'created_at' => $cuando])],
    'reportes de novedad' => [ListIssueReports::class, fn (Tenant $t, string $cuando): Model => EquipmentIssueReport::factory()->create(['tenant_id' => $t->id, 'created_at' => $cuando])],
    'paros' => [ListDowntimeEvents::class, function (Tenant $t, string $cuando): Model {
        $equipo = Equipment::factory()->create(['tenant_id' => $t->id]);

        return EquipmentDowntimeEvent::factory()->create(['tenant_id' => $t->id, 'plant_id' => $equipo->plant_id, 'equipment_id' => $equipo->id, 'started_at' => $cuando]);
    }],
    'calendario de producción' => [ListProductionCalendarDays::class, fn (Tenant $t, string $cuando): Model => ProductionCalendarDay::factory()->create(['tenant_id' => $t->id, 'calendar_date' => Carbon::parse($cuando)->toDateString()])],
    'presupuesto' => [ListMaintenanceBudgets::class, fn (Tenant $t, string $cuando): Model => MaintenanceBudget::factory()->create(['tenant_id' => $t->id, 'year' => Carbon::parse($cuando)->year, 'month' => Carbon::parse($cuando)->month])],
    'despachos' => [Despachos::class, fn (Tenant $t, string $cuando): Model => pedidoEnCola($t, $cuando)],
    'kardex' => [Kardex::class, fn (Tenant $t, string $cuando): Model => InventoryTransaction::factory()->create(['tenant_id' => $t->id, 'performed_at' => $cuando])],
    'conteos' => [ListInventoryCounts::class, fn (Tenant $t, string $cuando): Model => InventoryCount::factory()->create(['tenant_id' => $t->id, 'created_at' => $cuando])],
    'órdenes de compra' => [ListPurchaseOrders::class, fn (Tenant $t, string $cuando): Model => PurchaseOrder::factory()->create(['tenant_id' => $t->id, 'created_at' => $cuando])],
    'repuestos' => [ListSpareParts::class, fn (Tenant $t, string $cuando): Model => SparePart::factory()->create(['tenant_id' => $t->id, 'created_at' => $cuando])],
    'hallazgos' => [ListSstFindings::class, fn (Tenant $t, string $cuando): Model => SstFinding::factory()->create(['tenant_id' => $t->id, 'reported_on' => Carbon::parse($cuando)->toDateString()])],
    'alertas' => [ListAlerts::class, fn (Tenant $t, string $cuando): Model => Alert::forceCreate([
        'tenant_id' => $t->id, 'severity' => 'info', 'category' => 'inventory', 'title' => 'Alerta '.$cuando,
        'status' => AlertStatus::cases()[0]->value, 'created_at' => $cuando,
    ])],
    'comunicados' => [ListAnnouncements::class, fn (Tenant $t, string $cuando): Model => Announcement::factory()->create(['tenant_id' => $t->id, 'published_at' => $cuando])],
    'notificaciones' => [Notificaciones::class, fn (Tenant $t, string $cuando): Model => test()->admin->notifications()->create([
        'id' => (string) Str::uuid(), 'type' => 'aviso', 'data' => ['title' => 'Aviso '.$cuando], 'created_at' => $cuando,
    ])],
    'auditoría' => [ListAuditLogs::class, fn (Tenant $t, string $cuando): Model => AuditLog::query()->forceCreate([
        'id' => (string) Str::uuid(), 'tenant_id' => $t->id, 'user_id' => test()->admin->id, 'event' => 'updated',
        'auditable_type' => Equipment::class, 'auditable_id' => (string) Str::uuid(), 'created_at' => $cuando,
    ])],
]);

it('filters by month and downloads what it shows', function (string $pantalla, Closure $crear): void {
    $noviembre = $crear($this->tenant, '2026-11-05 10:00');
    $octubre = $crear($this->tenant, '2026-10-05 10:00');

    Livewire::test($pantalla)
        // Abre en «Todo»: la barra no esconde lo que la lista mostraba antes de tenerla.
        ->assertCanSeeTableRecords([$noviembre, $octubre])
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 11])
        ->assertCanSeeTableRecords([$noviembre])
        ->assertCanNotSeeTableRecords([$octubre])
        // Kardex conserva su propio Excel; las demás bajan el de la tabla.
        ->callAction(TestAction::make($pantalla === Kardex::class ? 'excel' : 'excelTabla')->table())
        ->assertFileDownloaded();
})->with('tablas con fecha');

it('also picks a whole year or a range', function (): void {
    $noviembre = pedidoEnCola($this->tenant, '2026-11-05 10:00');
    $anioPasado = pedidoEnCola($this->tenant, '2025-11-05 10:00');

    Livewire::test(Despachos::class)
        ->filterTable('periodo', ['preset' => 'anio', 'anio' => 2025])
        ->assertCanSeeTableRecords([$anioPasado])
        ->assertCanNotSeeTableRecords([$noviembre])
        ->filterTable('periodo', ['preset' => 'rango', 'desde' => '2026-11-01', 'hasta' => '2026-11-30'])
        ->assertCanSeeTableRecords([$noviembre])
        ->assertCanNotSeeTableRecords([$anioPasado]);
});

it('cuts the kardex day at midnight in Colombia', function (): void {
    // Las 22:00 del 31 de octubre en Colombia ya son el 1 de noviembre en UTC: es de octubre.
    $nocheDeOctubre = InventoryTransaction::factory()->create(['tenant_id' => $this->tenant->id, 'performed_at' => '2026-11-01 03:00']);
    $noviembre = InventoryTransaction::factory()->create(['tenant_id' => $this->tenant->id, 'performed_at' => '2026-11-01 06:00']);

    Livewire::test(Kardex::class)
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 11])
        ->assertCanSeeTableRecords([$noviembre])
        ->assertCanNotSeeTableRecords([$nocheDeOctubre]);
});

it('writes the period and real dates in the Excel, not «hace 11 días»', function (): void {
    pedidoEnCola($this->tenant, '2026-11-05 10:00');
    pedidoEnCola($this->tenant, '2026-10-05 10:00');

    $pantalla = Livewire::test(Despachos::class)
        ->filterTable('periodo', ['preset' => 'mes', 'anio' => 2026, 'mes' => 11]);

    $texto = textoDelLibro((new TableExcelExport)->build($pantalla->instance(), 'Despachos')->close());

    expect($texto)->toContain('Fecha del pedido: noviembre 2026')
        ->toContain('1 fila')
        ->toContain('05/11/2026')
        ->not->toContain('hace');
});

it('offers the last seven days outside payroll and says it in words', function (): void {
    expect(PayrollPeriodFilter::window(['preset' => 'ultimos_7']))->toBe(['2026-11-10', '2026-11-16'])
        ->and(PayrollPeriodFilter::label(['preset' => 'ultimos_7']))->toBe('Últimos 7 días');
});

it('keeps the full report next to the filtered Excel', function (): void {
    Livewire::test(ListWorkOrders::class)
        ->assertActionExists('export_excel')
        ->assertSee('Reporte completo (Excel)')
        ->assertSee('Descargar Excel');
});
