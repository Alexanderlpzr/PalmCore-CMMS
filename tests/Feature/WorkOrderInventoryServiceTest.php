<?php

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Maintenance\Enums\WorkOrderPartStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderInventoryService;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\InventoryTransaction;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: Warehouse, 2: SparePart, 3: User, 4: WorkOrder}
 */
function woInventorySetup(float $initialStock = 20.0, float $unitCost = 50.0): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
    $sparePart = SparePart::factory()->create([
        'tenant_id' => $tenant->id,
        'unit_cost' => $unitCost,
        'created_by' => $user->id,
    ]);

    // Seed initial stock
    WarehouseSparePart::firstOrCreate(
        ['warehouse_id' => $warehouse->id, 'spare_part_id' => $sparePart->id],
        [
            'tenant_id' => $tenant->id,
            'current_stock' => $initialStock,
            'reserved_stock' => 0,
            'average_unit_cost' => $unitCost,
        ]
    );

    $workOrder = app(WorkOrderService::class)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Test WO',
        'description' => 'Test',
        'equipment_stopped' => false,
    ], $user);

    return [$tenant, $warehouse, $sparePart, $user, $workOrder];
}

function addInventoryPart(WorkOrder $workOrder, Warehouse $warehouse, SparePart $sparePart, float $qty, float $unitCostSnapshot): WorkOrderPart
{
    return WorkOrderPart::create([
        'tenant_id' => $workOrder->tenant_id,
        'work_order_id' => $workOrder->id,
        'spare_part_id' => $sparePart->id,
        'warehouse_id' => $warehouse->id,
        'description' => $sparePart->name,
        'quantity' => $qty,
        'unit_cost_snapshot' => $unitCostSnapshot,
        'status' => WorkOrderPartStatus::Requested->value,
        'reserved_quantity' => 0,
        'issued_quantity' => 0,
        'returned_quantity' => 0,
    ]);
}

// ── 1. requested → reserved ───────────────────────────────────────────────────

it('transitions part from requested to reserved when WO moves to Planned', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 5.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);

    $part->refresh();
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($part->status)->toBe(WorkOrderPartStatus::Reserved)
        ->and((float) $part->reserved_quantity)->toBe(5.0)
        ->and((float) $wsp->reserved_stock)->toBe(5.0)
        ->and((float) $wsp->current_stock)->toBe(20.0); // stock not yet consumed
});

it('does not affect free-text parts (no spare_part_id) during reservation', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $service = app(WorkOrderService::class);

    // Free-text part — no inventory link
    $freePart = WorkOrderPart::create([
        'tenant_id' => $workOrder->tenant_id,
        'work_order_id' => $workOrder->id,
        'description' => 'Tuerca M10',
        'quantity' => 4,
        'status' => WorkOrderPartStatus::Requested->value,
        'reserved_quantity' => 0,
        'issued_quantity' => 0,
        'returned_quantity' => 0,
    ]);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);

    $freePart->refresh();

    // Free-text parts stay in requested — no inventory system interaction
    expect($freePart->status)->toBe(WorkOrderPartStatus::Requested);
});

// ── 2. reserved → issued ─────────────────────────────────────────────────────

it('transitions part from reserved to issued when WO moves to Completed', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup(initialStock: 20.0);
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 5.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);
    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $part->refresh();
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($part->status)->toBe(WorkOrderPartStatus::Issued)
        ->and((float) $part->issued_quantity)->toBe(5.0)
        ->and((float) $part->reserved_quantity)->toBe(0.0)
        ->and((float) $wsp->current_stock)->toBe(15.0)
        ->and((float) $wsp->reserved_stock)->toBe(0.0);
});

it('creates a Consumption InventoryTransaction when WO is Completed', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 3.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);
    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $tx = InventoryTransaction::where('work_order_id', $workOrder->id)
        ->where('type', InventoryTransactionType::Consumption->value)
        ->first();

    expect($tx)->not->toBeNull()
        ->and($tx->type)->toBe(InventoryTransactionType::Consumption)
        ->and((float) $tx->quantity)->toBe(-3.0)
        ->and((float) $tx->unit_cost)->toBe(50.0)
        ->and($tx->work_order_part_id)->toBe($part->id);
});

// ── 3. reserved → cancelled ──────────────────────────────────────────────────

it('releases reservation and marks part cancelled when WO is Cancelled', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 6.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);

    $wspAfterPlan = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();
    expect((float) $wspAfterPlan->reserved_stock)->toBe(6.0);

    $service->transition($workOrder, WorkOrderStatus::Cancelled, $user);

    $part->refresh();
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($part->status)->toBe(WorkOrderPartStatus::Cancelled)
        ->and((float) $part->reserved_quantity)->toBe(0.0)
        ->and((float) $wsp->reserved_stock)->toBe(0.0)
        ->and((float) $wsp->current_stock)->toBe(20.0); // stock untouched
});

// ── 4. issued → returned ─────────────────────────────────────────────────────

it('returns issued part back to warehouse and increments stock', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $service = app(WorkOrderService::class);
    $woInvService = app(WorkOrderInventoryService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 5.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);
    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $wspAfterConsume = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();
    expect((float) $wspAfterConsume->current_stock)->toBe(15.0);

    // Return 2 of the 5 issued units
    $woInvService->returnPartFromWorkOrder($part->refresh(), 2.0, $user);

    $part->refresh();
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($part->status)->toBe(WorkOrderPartStatus::Issued) // still issued — partial return
        ->and((float) $part->returned_quantity)->toBe(2.0)
        ->and((float) $part->issued_quantity)->toBe(5.0)
        ->and((float) $wsp->current_stock)->toBe(17.0);
});

it('marks part as returned when full quantity is returned', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $service = app(WorkOrderService::class);
    $woInvService = app(WorkOrderInventoryService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 4.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);
    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $woInvService->returnPartFromWorkOrder($part->refresh(), 4.0, $user);

    expect($part->refresh()->status)->toBe(WorkOrderPartStatus::Returned);
});

// ── 5. unit_cost_snapshot histórico ──────────────────────────────────────────

it('uses unit_cost_snapshot at time of issue even after spare part price changes', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup(unitCost: 100.0);
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 2.0, 100.0); // snapshot locked at $100

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);

    // Price changes BEFORE consumption
    $sparePart->update(['unit_cost' => 999.0]);

    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $tx = InventoryTransaction::where('work_order_id', $workOrder->id)
        ->where('type', InventoryTransactionType::Consumption->value)
        ->first();

    // Transaction must use the snapshot cost, not the new price
    expect((float) $tx->unit_cost)->toBe(100.0)
        ->and((float) $tx->total_cost)->toBe(200.0);
});

// ── 6. No stock negativo ──────────────────────────────────────────────────────

it('throws when trying to reserve more stock than available', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup(initialStock: 2.0);
    $service = app(WorkOrderService::class);

    addInventoryPart($workOrder, $warehouse, $sparePart, 5.0, 50.0); // 5 > available 2

    expect(fn () => $service->transition($workOrder, WorkOrderStatus::Planned, $user))
        ->toThrow(RuntimeException::class, 'Cannot reserve');
});

it('throws when trying to consume more than reserved (no stock added after reserve)', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup(initialStock: 10.0);
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 5.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user); // reserve 5
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);

    // Manually drain current_stock below the reserved amount to simulate a race condition
    WarehouseSparePart::where('spare_part_id', $sparePart->id)
        ->update(['current_stock' => 2]);

    expect(fn () => $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']))
        ->toThrow(RuntimeException::class);
});

// ── 7. No devolver más de lo emitido ─────────────────────────────────────────

it('throws when trying to return more than issued quantity', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $service = app(WorkOrderService::class);
    $woInvService = app(WorkOrderInventoryService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 4.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);
    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    expect(fn () => $woInvService->returnPartFromWorkOrder($part->refresh(), 5.0, $user)) // 5 > issued 4
        ->toThrow(RuntimeException::class, 'Cannot return');
});

it('throws when trying to return from a part that is not in issued status', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup();
    $woInvService = app(WorkOrderInventoryService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 4.0, 50.0);
    // Part is still 'requested' (no transitions yet)

    expect(fn () => $woInvService->returnPartFromWorkOrder($part, 1.0, $user))
        ->toThrow(RuntimeException::class, 'Only issued parts can be returned');
});

// ── 8. Integración completa OT → Inventario ──────────────────────────────────

it('completes full lifecycle: create → plan → execute → complete with correct inventory state', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup(initialStock: 50.0, unitCost: 75.0);
    $service = app(WorkOrderService::class);

    $part1 = addInventoryPart($workOrder, $warehouse, $sparePart, 10.0, 75.0);

    // Create a second spare part in the same warehouse
    $sparePart2 = SparePart::factory()->create(['tenant_id' => $tenant->id, 'unit_cost' => 30.0, 'created_by' => $user->id]);
    WarehouseSparePart::create([
        'tenant_id' => $tenant->id,
        'warehouse_id' => $warehouse->id,
        'spare_part_id' => $sparePart2->id,
        'current_stock' => 20.0,
        'reserved_stock' => 0,
        'average_unit_cost' => 30.0,
    ]);
    $part2 = addInventoryPart($workOrder, $warehouse, $sparePart2, 3.0, 30.0);

    // Draft → Planned: both parts reserved
    $service->transition($workOrder, WorkOrderStatus::Planned, $user);

    $wsp1 = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();
    $wsp2 = WarehouseSparePart::where('spare_part_id', $sparePart2->id)->first();

    expect($part1->refresh()->status)->toBe(WorkOrderPartStatus::Reserved)
        ->and($part2->refresh()->status)->toBe(WorkOrderPartStatus::Reserved)
        ->and((float) $wsp1->reserved_stock)->toBe(10.0)
        ->and((float) $wsp2->reserved_stock)->toBe(3.0);

    // Planned → InProgress: no inventory change
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);

    // InProgress → Completed: both parts consumed
    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $wsp1->refresh();
    $wsp2->refresh();

    expect($part1->refresh()->status)->toBe(WorkOrderPartStatus::Issued)
        ->and($part2->refresh()->status)->toBe(WorkOrderPartStatus::Issued)
        ->and((float) $wsp1->current_stock)->toBe(40.0) // 50 - 10
        ->and((float) $wsp1->reserved_stock)->toBe(0.0)
        ->and((float) $wsp2->current_stock)->toBe(17.0) // 20 - 3
        ->and((float) $wsp2->reserved_stock)->toBe(0.0);

    // Verify two Consumption transactions exist
    $txCount = InventoryTransaction::where('work_order_id', $workOrder->id)
        ->where('type', InventoryTransactionType::Consumption->value)
        ->count();

    expect($txCount)->toBe(2);
});

// ── 9. Rollback ante excepción ────────────────────────────────────────────────

it('rolls back entire transition when consumption fails mid-way', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup(initialStock: 10.0);
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 5.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);

    // Verify reservation succeeded
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();
    expect((float) $wsp->reserved_stock)->toBe(5.0);

    // Simulate a race condition: another process consumed the stock between reserve and consume
    WarehouseSparePart::where('spare_part_id', $sparePart->id)
        ->update(['current_stock' => 0]);

    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);

    // Attempt to complete — should throw (insufficient stock) and roll back entirely
    try {
        $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);
        expect(true)->toBeFalse('Expected exception was not thrown');
    } catch (RuntimeException $e) {
        // Expected
    }

    // WO status must have rolled back to InProgress
    expect($workOrder->fresh()->status)->toBe(WorkOrderStatus::InProgress);

    // Part must still be in reserved status
    expect($part->fresh()->status)->toBe(WorkOrderPartStatus::Reserved);

    // No Consumption transaction should exist
    $txCount = InventoryTransaction::where('work_order_id', $workOrder->id)
        ->where('type', InventoryTransactionType::Consumption->value)
        ->count();
    expect($txCount)->toBe(0);
});

// ── 10. Aislamiento multiempresa ──────────────────────────────────────────────

it('inventory operations on one tenant do not affect another tenant stock', function () {
    $user = User::factory()->create();

    // Tenant A setup
    $tenantA = Tenant::factory()->create();
    $equipmentA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenantA->id, 'created_by' => $user->id]);
    $sparePartA = SparePart::factory()->create(['tenant_id' => $tenantA->id, 'unit_cost' => 50.0, 'created_by' => $user->id]);
    WarehouseSparePart::create([
        'tenant_id' => $tenantA->id, 'warehouse_id' => $warehouseA->id, 'spare_part_id' => $sparePartA->id,
        'current_stock' => 20.0, 'reserved_stock' => 0, 'average_unit_cost' => 50.0,
    ]);

    // Tenant B setup — same spare part code, completely separate stock
    $tenantB = Tenant::factory()->create();
    $equipmentB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenantB->id, 'created_by' => $user->id]);
    $sparePartB = SparePart::factory()->create(['tenant_id' => $tenantB->id, 'unit_cost' => 50.0, 'created_by' => $user->id]);
    WarehouseSparePart::create([
        'tenant_id' => $tenantB->id, 'warehouse_id' => $warehouseB->id, 'spare_part_id' => $sparePartB->id,
        'current_stock' => 30.0, 'reserved_stock' => 0, 'average_unit_cost' => 50.0,
    ]);

    $service = app(WorkOrderService::class);

    $woA = $service->create([
        'tenant_id' => $tenantA->id, 'equipment_id' => $equipmentA->id,
        'work_order_type' => WorkOrderType::Corrective->value, 'priority' => 'p3_medium',
        'title' => 'WO Tenant A', 'description' => 'desc', 'equipment_stopped' => false,
    ], $user);

    addInventoryPart($woA, $warehouseA, $sparePartA, 7.0, 50.0);
    $service->transition($woA, WorkOrderStatus::Planned, $user);

    // Tenant B's stock must be completely unaffected
    $wspB = WarehouseSparePart::where('spare_part_id', $sparePartB->id)->first();
    $wspA = WarehouseSparePart::where('spare_part_id', $sparePartA->id)->first();

    expect((float) $wspA->reserved_stock)->toBe(7.0)   // A affected
        ->and((float) $wspB->reserved_stock)->toBe(0.0) // B untouched
        ->and((float) $wspB->current_stock)->toBe(30.0); // B untouched
});

// ── Bonus: supervisor reject_completion undoes consumption ────────────────────

it('undoes consumption and re-reserves parts when supervisor rejects completion', function () {
    [$tenant, $warehouse, $sparePart, $user, $workOrder] = woInventorySetup(initialStock: 20.0);
    $service = app(WorkOrderService::class);

    $part = addInventoryPart($workOrder, $warehouse, $sparePart, 5.0, 50.0);

    $service->transition($workOrder, WorkOrderStatus::Planned, $user);
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user);
    $service->transition($workOrder, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();
    expect((float) $wsp->current_stock)->toBe(15.0)
        ->and($part->refresh()->status)->toBe(WorkOrderPartStatus::Issued);

    // Supervisor rejects: Completed → InProgress
    $service->transition($workOrder, WorkOrderStatus::InProgress, $user, ['rejection_reason' => 'Work incomplete']);

    $part->refresh();
    $wsp->refresh();

    // Stock must be restored + part re-reserved
    expect((float) $wsp->current_stock)->toBe(20.0)
        ->and((float) $wsp->reserved_stock)->toBe(5.0)
        ->and($part->status)->toBe(WorkOrderPartStatus::Reserved)
        ->and((float) $part->reserved_quantity)->toBe(5.0)
        ->and((float) $part->issued_quantity)->toBe(0.0);

    // Return transaction exists (audit trail of the reversal)
    $returnTx = InventoryTransaction::where('work_order_id', $workOrder->id)
        ->where('type', InventoryTransactionType::Return->value)
        ->first();
    expect($returnTx)->not->toBeNull();
});

// ── Emergency WO: auto-reserve + consume skipping Planned ─────────────────────

it('auto-reserves and consumes parts for emergency WO that skips Planned', function () {
    [$tenant, $warehouse, $sparePart, $user] = woInventorySetup(initialStock: 20.0);
    $service = app(WorkOrderService::class);
    $equipment = Equipment::where('tenant_id', $tenant->id)->first();

    // Emergency WO starts directly InProgress (skips Draft/Planned)
    $emergencyWo = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Emergency->value,
        'priority' => 'p1_critical',
        'title' => 'Emergencia',
        'description' => 'desc',
        'equipment_stopped' => false,
    ], $user);

    expect($emergencyWo->status)->toBe(WorkOrderStatus::InProgress);

    // Parts added while WO is already InProgress — stay in requested
    $part = addInventoryPart($emergencyWo, $warehouse, $sparePart, 3.0, 50.0);
    expect($part->status)->toBe(WorkOrderPartStatus::Requested);

    // Complete: auto-reserve + consume in one step
    $service->transition($emergencyWo, WorkOrderStatus::Completed, $user, ['work_performed' => 'done']);

    $part->refresh();
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($part->status)->toBe(WorkOrderPartStatus::Issued)
        ->and((float) $wsp->current_stock)->toBe(17.0)
        ->and((float) $wsp->reserved_stock)->toBe(0.0);
});
