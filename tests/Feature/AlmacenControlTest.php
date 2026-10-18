<?php

use App\Actions\Tenants\ProvisionTenantBaseStructure;
use App\Domain\Automation\Enums\AutomationEventType;
use App\Domain\Automation\Services\AutomationService;
use App\Domain\Inventory\Enums\InventoryCountScope;
use App\Domain\Inventory\Enums\InventoryCountStatus;
use App\Domain\Inventory\Enums\InventoryExitReason;
use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Services\InventoryCountService;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Inventory\Services\SparePartService;
use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Domain\Inventory\Services\WarehouseReports;
use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderInventoryService;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Reports\Services\SparePartLabelsPdfService;
use App\Filament\Pages\Despachos;
use App\Filament\Pages\Kardex;
use App\Filament\Pages\ReportesAlmacen;
use App\Filament\Pages\TableroAlmacen;
use App\Filament\Resources\Inventory\InventoryCount\Pages\CreateInventoryCount;
use App\Filament\Resources\Inventory\InventoryCount\Pages\ViewInventoryCount;
use App\Filament\Resources\Inventory\InventoryCount\RelationManagers\LinesRelationManager;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\ViewPurchaseOrder;
use App\Filament\Resources\Inventory\SparePart\Pages\CreateSparePart;
use App\Filament\Resources\Inventory\SparePart\Pages\ListSpareParts;
use App\Filament\Resources\Inventory\SparePart\Pages\ViewSparePart;
use App\Filament\Resources\Inventory\SparePart\RelationManagers\EquipmentUsageRelationManager;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Alert;
use App\Models\AutomationRule;
use App\Models\Equipment;
use App\Models\EquipmentSparePart;
use App\Models\InventoryCount;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El control del almacén, fase por fase: la alerta de reposición, el levantamiento en el
 * estante, el despacho a las órdenes, los vales de salida, los conteos y los reportes.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $this->almacen = Warehouse::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Almacén principal']);

    $this->bodeguero = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $this->bodeguero->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->bodeguero->assignRole('almacen');
});

function repuestoConExistencia(Tenant $tenant, Warehouse $warehouse, array $attributes, float $stock, float $cost = 10_000): SparePart
{
    $part = SparePart::factory()->create([
        'tenant_id' => $tenant->id,
        'minimum_stock' => null,
        'maximum_stock' => null,
        'reorder_point' => null,
        'reorder_quantity' => null,
        'unit_cost' => $cost,
        ...$attributes,
    ]);

    WarehouseSparePart::factory()->create([
        'tenant_id' => $tenant->id, 'warehouse_id' => $warehouse->id, 'spare_part_id' => $part->id,
        'current_stock' => $stock, 'reserved_stock' => 0, 'average_unit_cost' => $cost,
    ]);

    return $part;
}

// ── Fase 0: la alerta de reposición ──────────────────────────────────────────

it('turns on the restock alert for every new company', function (): void {
    $empresa = Tenant::factory()->create();
    app(ProvisionTenantBaseStructure::class)->handle($empresa);
    app(ProvisionTenantBaseStructure::class)->handle($empresa);

    $reglas = AutomationRule::withoutGlobalScopes()->where('tenant_id', $empresa->id)
        ->where('event_type', AutomationEventType::StockLow->value)->get();

    expect($reglas)->toHaveCount(1)
        ->and($reglas->first()->isActionable())->toBeTrue();
});

it('does not bring back a restock alert someone deleted', function (): void {
    AutomationRule::ensureStockLowRule($this->tenant->id);
    AutomationRule::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail()->delete();

    AutomationRule::ensureStockLowRule($this->tenant->id);

    expect(AutomationRule::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereNull('deleted_at')->count())->toBe(0);
});

it('alerts on the minimum when the part has no reorder point', function (): void {
    Notification::fake();
    AutomationRule::ensureStockLowRule($this->tenant->id);

    $enElMinimo = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Rodamiento 6205', 'minimum_stock' => 5], 5);
    repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Correa B-52', 'minimum_stock' => 2], 8);
    repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Sello mecánico'], 0);

    $regla = AutomationRule::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
    app(AutomationService::class)->executeRule($regla);

    $alertas = Alert::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('title');

    expect($alertas)->toHaveCount(1)
        ->and($alertas->first())->toContain($enElMinimo->code);
});

// ── Fase 1: el levantamiento ─────────────────────────────────────────────────

it('gives each new part the next code of its category', function (): void {
    $servicio = app(SparePartService::class);
    $datos = fn (string $categoria, ?string $codigo = null): array => [
        'tenant_id' => $this->tenant->id, 'code' => $codigo, 'name' => 'Repuesto', 'category_type' => $categoria,
        'criticality' => 'medium', 'unit' => 'unidad', 'unit_cost' => 0,
    ];

    $primero = $servicio->create($datos('mechanical'), $this->bodeguero);
    $servicio->create($datos('mechanical', 'MEC-0007'), $this->bodeguero);
    $siguiente = $servicio->create($datos('mechanical'), $this->bodeguero);
    $electrico = $servicio->create($datos('electrical'), $this->bodeguero);
    $propio = $servicio->create($datos('electrical', 'BOMBA-01'), $this->bodeguero);

    // Otra empresa lleva su propia cuenta.
    $otra = Tenant::factory()->create();
    $ajeno = $servicio->create([...$datos('mechanical'), 'tenant_id' => $otra->id], $this->bodeguero);

    expect($primero->code)->toBe('MEC-0001')
        ->and($siguiente->code)->toBe('MEC-0008')
        ->and($electrico->code)->toBe('ELE-0001')
        ->and($propio->code)->toBe('BOMBA-01')
        ->and($ajeno->code)->toBe('MEC-0001');
});

it('lets the spare part form leave the code empty', function (): void {
    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(CreateSparePart::class)
        ->fillForm([
            'name' => 'Guante de nitrilo', 'category_type' => 'safety', 'criticality' => 'low', 'unit' => 'par',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SparePart::query()->where('name', 'Guante de nitrilo')->value('code'))->toBe('SEG-0001');
});

it('surveys a shelf from the dashboard: code, location and initial stock in one step', function (): void {
    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(TableroAlmacen::class)
        ->callAction('levantar', [
            'name' => 'Rodamiento 6205 2RS', 'category_type' => 'mechanical', 'unit' => 'unidad',
            'warehouse_id' => $this->almacen->id, 'bin_location' => 'Estante A-2', 'quantity' => 6,
            'unit_cost' => 18_000, 'minimum_stock' => 2, 'criticality' => 'high',
        ], arguments: ['otro' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('MEC-0001 · Rodamiento 6205 2RS')
        // «Guardar y levantar otro» deja el formulario abierto en el mismo estante.
        ->assertActionMounted('levantar')
        ->assertSchemaStateSet(['bin_location' => 'Estante A-2', 'warehouse_id' => $this->almacen->id], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasActionErrors(['name', 'quantity']);

    $parte = SparePart::query()->where('code', 'MEC-0001')->firstOrFail();
    $estante = WarehouseSparePart::query()->where('spare_part_id', $parte->id)->firstOrFail();
    $movimiento = InventoryTransaction::query()->where('spare_part_id', $parte->id)->firstOrFail();

    expect((float) $estante->current_stock)->toBe(6.0)
        ->and((float) $estante->average_unit_cost)->toBe(18_000.0)
        ->and($estante->bin_location)->toBe('Estante A-2')
        ->and($estante->last_counted_by)->toBe($this->bodeguero->id)
        ->and($movimiento->reference_number)->toBe('Inventario inicial')
        ->and((float) $parte->minimum_stock)->toBe(2.0);
});

it('records an empty shelf spot without a movement', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Correa B-52'], 0);
    WarehouseSparePart::query()->where('spare_part_id', $parte->id)->delete();

    $estante = app(InventoryService::class)->registerInitialCount($this->almacen, $parte, 0, 0, $this->bodeguero, 'Estante C-1');

    expect($estante->bin_location)->toBe('Estante C-1')
        ->and((float) $estante->current_stock)->toBe(0.0)
        ->and(InventoryTransaction::query()->where('spare_part_id', $parte->id)->exists())->toBeFalse();
});

// ── Fase 2: el vale de salida, el despacho y la recepción ────────────────────

function ordenConRepuesto(Tenant $tenant, Warehouse $warehouse, SparePart $part, float $quantity): WorkOrderPart
{
    $orden = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => Equipment::factory()->create(['tenant_id' => $tenant->id])->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);

    return WorkOrderPart::create([
        'tenant_id' => $tenant->id, 'work_order_id' => $orden->id, 'spare_part_id' => $part->id, 'warehouse_id' => $warehouse->id,
        'description' => $part->name, 'quantity' => $quantity, 'unit_cost_snapshot' => 10_000,
        'status' => WorkOrderPartStatus::Requested->value, 'reserved_quantity' => 0, 'issued_quantity' => 0, 'returned_quantity' => 0,
    ]);
}

it('asks who takes the part when it leaves for plant use, but not for a write-off', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Guantes de vaqueta'], 10);
    $servicio = app(InventoryService::class);

    expect(fn () => $servicio->recordExit($this->almacen, $parte, 2, 10_000, $this->bodeguero, exitReason: InventoryExitReason::ProtectiveEquipment))
        ->toThrow(InvalidArgumentException::class);

    $vale = $servicio->recordExit($this->almacen, $parte, 2, 10_000, $this->bodeguero, exitReason: InventoryExitReason::ProtectiveEquipment, recipientName: 'Pedro Gómez');
    $baja = $servicio->recordExit($this->almacen, $parte, 1, 10_000, $this->bodeguero, exitReason: InventoryExitReason::DamageOrLoss);

    expect($vale->exit_reason)->toBe(InventoryExitReason::ProtectiveEquipment)
        ->and($vale->recipient_name)->toBe('Pedro Gómez')
        ->and($baja->recipient_name)->toBeNull();
});

it('fills the exit voucher from the dashboard', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Grasa EP2'], 10);
    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(TableroAlmacen::class)->callAction('salida', [
        'warehouse_id' => $this->almacen->id, 'spare_part_id' => $parte->id, 'quantity' => 1, 'exit_reason' => 'plant_consumption',
    ])->assertHasActionErrors(['recipient_name' => 'required']);

    Livewire::test(TableroAlmacen::class)->callAction('salida', [
        'warehouse_id' => $this->almacen->id, 'spare_part_id' => $parte->id, 'quantity' => 1,
        'exit_reason' => 'plant_consumption', 'recipient_name' => 'Luis Pérez',
    ])->assertNotified('Movimiento registrado');

    expect(InventoryTransaction::query()->where('spare_part_id', $parte->id)->value('recipient_name'))->toBe('Luis Pérez');
});

it('leaves the parts of a closed work order waiting for the warehouse when it dispatches', function (): void {
    $this->tenant->update(['settings' => ['inventory' => ['warehouse_dispatch' => true]]]);
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Rodamiento 6310'], 5);
    $pedido = ordenConRepuesto($this->tenant, $this->almacen, $parte, 2);

    app(WorkOrderService::class)->transition($pedido->workOrder, WorkOrderStatus::Completed, $this->bodeguero, ['work_performed' => 'Cambio de rodamiento']);

    expect($pedido->refresh()->status)->toBe(WorkOrderPartStatus::Requested)
        ->and((float) WarehouseSparePart::query()->where('spare_part_id', $parte->id)->value('current_stock'))->toBe(5.0)
        ->and(app(WarehouseDashboard::class)->attention($this->tenant)['pendingDispatches'])->toBe(1);
});

it('still consumes on completion where the warehouse does not dispatch', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Rodamiento 6310'], 5);
    $pedido = ordenConRepuesto($this->tenant, $this->almacen, $parte, 2);

    app(WorkOrderService::class)->transition($pedido->workOrder, WorkOrderStatus::Completed, $this->bodeguero, ['work_performed' => 'Cambio de rodamiento']);

    expect($pedido->refresh()->status)->toBe(WorkOrderPartStatus::Issued);
});

it('dispatches from the queue: less than requested frees the rest, and records who received it', function (): void {
    $this->tenant->update(['settings' => ['inventory' => ['warehouse_dispatch' => true]]]);
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Correa B-52'], 6);
    $pedido = ordenConRepuesto($this->tenant, $this->almacen, $parte, 4);
    $otro = ordenConRepuesto($this->tenant, $this->almacen, repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Sello'], 3), 1);

    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(Despachos::class)
        ->assertCanSeeTableRecords([$pedido, $otro])
        ->callTableAction('entregar', $pedido, ['quantity' => 3, 'recipient_name' => 'Carlos Ruiz'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Repuesto entregado')
        ->callTableAction('noEntregado', $otro)
        ->assertCanNotSeeTableRecords([$pedido, $otro]);

    $estante = WarehouseSparePart::query()->where('spare_part_id', $parte->id)->firstOrFail();
    $consumo = InventoryTransaction::query()->where('work_order_part_id', $pedido->id)->firstOrFail();

    expect($pedido->refresh()->status)->toBe(WorkOrderPartStatus::Issued)
        ->and((float) $pedido->issued_quantity)->toBe(3.0)
        ->and((float) $estante->current_stock)->toBe(3.0)
        ->and((float) $estante->reserved_stock)->toBe(0.0)
        ->and($consumo->recipient_name)->toBe('Carlos Ruiz')
        ->and($otro->refresh()->status)->toBe(WorkOrderPartStatus::Cancelled);
});

it('does not dispatch what is not on the shelf', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Motor 5 HP'], 1);
    $pedido = ordenConRepuesto($this->tenant, $this->almacen, $parte, 2);

    expect(fn () => app(WorkOrderInventoryService::class)->dispatchPart($pedido, 2, 'Carlos Ruiz', $this->bodeguero))
        ->toThrow(RuntimeException::class);

    expect($pedido->refresh()->status)->toBe(WorkOrderPartStatus::Requested)
        ->and((float) WarehouseSparePart::query()->where('spare_part_id', $parte->id)->value('reserved_stock'))->toBe(0.0);
});

it('lets the warehouse dispatch only with the exit permission', function (): void {
    $this->actingAs($this->bodeguero);
    expect(Despachos::canAccess())->toBeTrue();

    $mantenimiento = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $mantenimiento->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $mantenimiento->assignRole('mantenimiento');
    $this->actingAs($mantenimiento);

    expect(Despachos::canAccess())->toBeFalse();
});

it('receives a purchase order line by line, leaving the rest pending', function (): void {
    $orden = PurchaseOrder::factory()->sent()->create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->almacen->id]);
    $rodamiento = PurchaseOrderLine::factory()->create([
        'tenant_id' => $this->tenant->id, 'purchase_order_id' => $orden->id, 'quantity_ordered' => 10, 'unit_cost' => 5_000,
        'spare_part_id' => SparePart::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);
    $correa = PurchaseOrderLine::factory()->create([
        'tenant_id' => $this->tenant->id, 'purchase_order_id' => $orden->id, 'quantity_ordered' => 2, 'unit_cost' => 30_000,
        'spare_part_id' => SparePart::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);

    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ViewPurchaseOrder::class, ['record' => $orden->getRouteKey()])
        ->callAction('receive', [
            'invoice' => 'FE-981',
            'lines' => [
                ['line_id' => $rodamiento->id, 'pending' => 10, 'quantity' => 4],
                ['line_id' => $correa->id, 'pending' => 2, 'quantity' => 0],
            ],
        ])
        ->assertNotified('Repuestos ingresados al almacén.');

    expect((float) $rodamiento->refresh()->quantity_received)->toBe(4.0)
        ->and((float) $correa->refresh()->quantity_received)->toBe(0.0)
        ->and($orden->refresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived)
        ->and(InventoryTransaction::query()->where('spare_part_id', $rodamiento->spare_part_id)->value('notes'))->toContain('factura FE-981');
});

// ── Fase 3: los conteos y el kardex ──────────────────────────────────────────

function contadoHace(SparePart $part, ?int $days): void
{
    WarehouseSparePart::query()->where('spare_part_id', $part->id)->update(['last_counted_at' => $days === null ? null : now()->subDays($days)]);
}

it('picks what is due for a cycle count by ABC class', function (): void {
    $aVencido = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'A vencido', 'abc_classification' => 'A'], 3);
    $aAlDia = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'A al día', 'abc_classification' => 'A'], 3);
    $cNunca = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'C nunca', 'abc_classification' => 'C'], 3);
    $cAlDia = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'C al día', 'abc_classification' => null], 3);
    contadoHace($aVencido, 40);
    contadoHace($aAlDia, 10);
    contadoHace($cNunca, null);
    contadoHace($cAlDia, 100);

    $conteo = app(InventoryCountService::class)->start($this->almacen, InventoryCountScope::Due, $this->bodeguero);

    expect($conteo->count_number)->toBe('CNT-'.date('Y').'-0001')
        ->and($conteo->lines()->pluck('spare_part_id')->sort()->values()->all())->toBe(collect([$aVencido->id, $cNunca->id])->sort()->values()->all())
        ->and(app(InventoryCountService::class)->dueCount($this->tenant->id))->toBe(2);
});

it('applies a count: differences become adjustments and what was not counted is left alone', function (): void {
    $faltante = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Faltante'], 10, 2_000);
    $cuadra = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Cuadra'], 4);
    $sinContar = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Sin contar'], 7);
    $servicio = app(InventoryCountService::class);

    $conteo = $servicio->start($this->almacen, InventoryCountScope::All, $this->bodeguero);
    $linea = fn (SparePart $part) => $conteo->lines()->where('spare_part_id', $part->id)->firstOrFail();
    $servicio->record($linea($faltante), 8, $this->bodeguero);
    $servicio->record($linea($cuadra), 4, $this->bodeguero);

    $resumen = $servicio->apply($conteo, $this->bodeguero);
    $existencia = fn (SparePart $part): float => (float) WarehouseSparePart::query()->where('spare_part_id', $part->id)->value('current_stock');

    expect($resumen)->toBe(['counted' => 2, 'adjusted' => 1, 'value' => -4_000.0])
        ->and($existencia($faltante))->toBe(8.0)
        ->and($existencia($sinContar))->toBe(7.0)
        ->and(WarehouseSparePart::query()->where('spare_part_id', $cuadra->id)->value('last_counted_at'))->not->toBeNull()
        ->and(WarehouseSparePart::query()->where('spare_part_id', $sinContar->id)->value('last_counted_at'))->toBeNull()
        ->and($conteo->refresh()->status)->toBe(InventoryCountStatus::Applied)
        ->and(InventoryTransaction::query()->where('spare_part_id', $faltante->id)->value('notes'))->toBe('Conteo '.$conteo->count_number);

    expect(fn () => $servicio->apply($conteo, $this->bodeguero))->toThrow(RuntimeException::class);
});

it('counts blind from the screen and applies it', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Rodamiento 6205', 'abc_classification' => 'A'], 10);
    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(CreateInventoryCount::class)
        ->fillForm(['warehouse_id' => $this->almacen->id, 'scope' => 'all'])
        ->call('create')
        ->assertHasNoFormErrors();

    $conteo = InventoryCount::query()->firstOrFail();
    $linea = $conteo->lines()->firstOrFail();

    Livewire::test(LinesRelationManager::class, ['ownerRecord' => $conteo, 'pageClass' => ViewInventoryCount::class])
        ->assertCanSeeTableRecords([$linea])
        ->assertCanNotRenderTableColumn('expected_quantity')
        ->call('updateTableColumnState', 'counted_quantity', $linea->getKey(), '9')
        ->callTableAction('diferencias')
        ->assertCanRenderTableColumn('expected_quantity');

    expect((float) $linea->refresh()->counted_quantity)->toBe(9.0);

    Livewire::test(ViewInventoryCount::class, ['record' => $conteo->getRouteKey()])
        ->callAction('planilla')
        ->assertFileDownloaded('CONTEO-'.$conteo->count_number.'.xlsx')
        ->callAction('aplicar')
        ->assertNotified('Conteo aplicado');

    expect((float) WarehouseSparePart::query()->where('spare_part_id', $parte->id)->value('current_stock'))->toBe(9.0);
});

it('shows the warehouse kardex and downloads it as filtered', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Grasa EP2'], 0);
    $servicio = app(InventoryService::class);
    $entrada = $servicio->receiveEntry($this->almacen, $parte, 5, 12_000, $this->bodeguero, 'FAC-77');
    $salida = $servicio->recordExit($this->almacen, $parte, 2, 12_000, $this->bodeguero, exitReason: InventoryExitReason::PlantConsumption, recipientName: 'Ana Torres');

    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    expect(Kardex::canAccess())->toBeTrue();

    Livewire::test(Kardex::class)
        ->assertCanSeeTableRecords([$entrada, $salida])
        ->assertSee('Ana Torres')
        ->filterTable('type', ['exit'])
        ->assertCanSeeTableRecords([$salida])
        ->assertCanNotSeeTableRecords([$entrada])
        ->callTableAction('excel')
        ->assertFileDownloaded('KARDEX-'.now()->format('Ymd').'.xlsx');
});

// ── Fase 4: reportes, dónde se usa y etiquetas ───────────────────────────────

it('classifies parts by consumption value and applies it', function (): void {
    $caro = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Caro', 'abc_classification' => 'C'], 20, 10_000);
    $medio = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Medio', 'abc_classification' => 'C'], 20, 10_000);
    $barato = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Barato', 'abc_classification' => 'A'], 20, 10_000);
    $quieto = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Quieto', 'abc_classification' => 'A'], 5, 10_000);
    $servicio = app(InventoryService::class);
    // Caro 85 % del valor consumido, Medio 10 %, Barato 5 %.
    $servicio->recordExit($this->almacen, $caro, 17, 10_000, $this->bodeguero, exitReason: InventoryExitReason::DamageOrLoss);
    $servicio->recordExit($this->almacen, $medio, 2, 10_000, $this->bodeguero, exitReason: InventoryExitReason::DamageOrLoss);
    $servicio->recordExit($this->almacen, $barato, 1, 10_000, $this->bodeguero, exitReason: InventoryExitReason::DamageOrLoss);

    $reportes = app(WarehouseReports::class);
    $clases = $reportes->abc($this->tenant)->mapWithKeys(fn (array $fila): array => [$fila['name'] => $fila['proposed']->value]);

    expect($clases->all())->toBe(['Caro' => 'A', 'Medio' => 'B', 'Barato' => 'C', 'Quieto' => 'C'])
        ->and($reportes->applyAbc($this->tenant))->toBe(4)
        ->and($caro->refresh()->abc_classification->value)->toBe('A')
        ->and($quieto->refresh()->abc_classification->value)->toBe('C')
        ->and($reportes->idle($this->tenant)->pluck('name')->all())->toBe(['Quieto'])
        ->and($reportes->exits($this->tenant)['reasons']->first())->toBe(['reason' => 'Daño o pérdida', 'value' => 200_000.0, 'movements' => 3]);
});

it('adds up what each equipment took and shows the reports page', function (): void {
    $this->tenant->update(['settings' => ['inventory' => ['warehouse_dispatch' => true]]]);
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Correa B-52'], 6, 30_000);
    $pedido = ordenConRepuesto($this->tenant, $this->almacen, $parte, 2);
    app(WorkOrderInventoryService::class)->dispatchPart($pedido, 2, 'Carlos Ruiz', $this->bodeguero);

    $porEquipo = app(WarehouseReports::class)->byEquipment($this->tenant);

    expect($porEquipo)->toHaveCount(1)
        ->and($porEquipo->first()['equipment'])->toContain($pedido->workOrder->equipment->name)
        ->and($porEquipo->first()['value'])->toBe(20_000.0);

    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ReportesAlmacen::class)
        ->assertOk()
        ->assertSee('ABC por consumo')
        ->assertSee('Correa B-52')
        ->callAction('aplicarAbc')
        ->assertNotified();
});

it('links a part on the equipment list to the warehouse and shows where it is used', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Rodamiento 6310'], 4);
    $equipo = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Prensa 1']);
    $enlace = EquipmentSparePart::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $equipo->id, 'spare_part_id' => $parte->id, 'name' => 'Rodamiento del motor',
    ]);

    expect($parte->equipmentUsage()->pluck('id')->all())->toBe([$enlace->id]);

    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    expect(EquipmentUsageRelationManager::canViewForRecord($parte, ViewSparePart::class))->toBeTrue();

    Livewire::test(EquipmentUsageRelationManager::class, ['ownerRecord' => $parte, 'pageClass' => ViewSparePart::class])
        ->assertCanSeeTableRecords([$enlace])
        ->assertSee('Prensa 1');
});

it('prints shelf labels with a QR for the selected parts', function (): void {
    $parte = repuestoConExistencia($this->tenant, $this->almacen, ['name' => 'Rodamiento 6205'], 4);
    WarehouseSparePart::query()->where('spare_part_id', $parte->id)->update(['bin_location' => 'Estante A-2']);

    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(ListSpareParts::class)
        ->callTableBulkAction('etiquetas', [$parte])
        ->assertFileDownloaded('ETIQUETAS-REPUESTOS-'.now()->format('Ymd').'.pdf');

    $pdf = app(SparePartLabelsPdfService::class)->generate(collect([$parte]), $this->tenant);

    expect($pdf)->toStartWith('%PDF');
});
