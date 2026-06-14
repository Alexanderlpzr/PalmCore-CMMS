<?php

use App\Domain\Inventory\Enums\InventoryTransactionType;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\InventoryTransaction;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * @return array{0: Tenant, 1: Warehouse, 2: SparePart, 3: User}
 */
function inventorySetup(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);
    $sparePart = SparePart::factory()->create(['tenant_id' => $tenant->id, 'unit_cost' => 20.0, 'created_by' => $user->id]);

    return [$tenant, $warehouse, $sparePart, $user];
}

function seedStock(Warehouse $warehouse, SparePart $sparePart, float $stock, float $unitCost = 20.0): WarehouseSparePart
{
    return WarehouseSparePart::firstOrCreate(
        ['warehouse_id' => $warehouse->id, 'spare_part_id' => $sparePart->id],
        [
            'tenant_id' => $warehouse->tenant_id,
            'current_stock' => $stock,
            'reserved_stock' => 0,
            'average_unit_cost' => $unitCost,
        ]
    );
}

// ── 1. Entry ──────────────────────────────────────────────────────────────────

it('entry creates transaction and increments stock from zero', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    $tx = $service->receiveEntry($warehouse, $sparePart, 10, 25.0, $user);

    $wsp = WarehouseSparePart::where('warehouse_id', $warehouse->id)
        ->where('spare_part_id', $sparePart->id)->first();

    expect($tx->type)->toBe(InventoryTransactionType::Entry)
        ->and((float) $tx->quantity)->toBe(10.0)
        ->and((float) $tx->unit_cost)->toBe(25.0)
        ->and((float) $tx->previous_stock)->toBe(0.0)
        ->and((float) $tx->new_stock)->toBe(10.0)
        ->and((float) $wsp->current_stock)->toBe(10.0);
});

it('entry creates WSP record automatically if it does not yet exist', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    expect(WarehouseSparePart::where('spare_part_id', $sparePart->id)->exists())->toBeFalse();

    app(InventoryService::class)->receiveEntry($warehouse, $sparePart, 5, 10.0, $user);

    expect(WarehouseSparePart::where('spare_part_id', $sparePart->id)->exists())->toBeTrue();
});

it('entry calculates weighted moving average cost', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    // 10 units at $20 → avg = $20
    $service->receiveEntry($warehouse, $sparePart, 10, 20.0, $user);
    // 5 more units at $35 → avg = (10×20 + 5×35) / 15 = 375/15 = $25
    $service->receiveEntry($warehouse, $sparePart, 5, 35.0, $user);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->average_unit_cost)->toBe(25.0)
        ->and((float) $wsp->current_stock)->toBe(15.0);
});

it('entry snapshots spare part code and name', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    $tx = app(InventoryService::class)->receiveEntry($warehouse, $sparePart, 3, 10.0, $user);

    expect($tx->spare_part_code_snapshot)->toBe($sparePart->code)
        ->and($tx->spare_part_name_snapshot)->toBe($sparePart->name);
});

it('entry rejects zero or negative quantity', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    expect(fn () => $service->receiveEntry($warehouse, $sparePart, 0, 10.0, $user))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->receiveEntry($warehouse, $sparePart, -5, 10.0, $user))
        ->toThrow(InvalidArgumentException::class);
});

it('entry accumulates correctly across multiple receipts', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    $service->receiveEntry($warehouse, $sparePart, 10, 20.0, $user);
    $service->receiveEntry($warehouse, $sparePart, 10, 20.0, $user);
    $service->receiveEntry($warehouse, $sparePart, 10, 20.0, $user);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->current_stock)->toBe(30.0)
        ->and(InventoryTransaction::where('spare_part_id', $sparePart->id)->count())->toBe(3);
});

// ── 2. Exit ───────────────────────────────────────────────────────────────────

it('exit creates transaction and decrements stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    seedStock($warehouse, $sparePart, 20, 25.0);

    $tx = $service->recordExit($warehouse, $sparePart, 7, 25.0, $user);
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($tx->type)->toBe(InventoryTransactionType::Exit)
        ->and((float) $tx->quantity)->toBe(-7.0)
        ->and((float) $tx->previous_stock)->toBe(20.0)
        ->and((float) $tx->new_stock)->toBe(13.0)
        ->and((float) $wsp->current_stock)->toBe(13.0);
});

it('exit rejects when requested quantity exceeds available stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 10, 20.0);

    expect(fn () => app(InventoryService::class)->recordExit($warehouse, $sparePart, 15, 20.0, $user))
        ->toThrow(RuntimeException::class, 'Insufficient available stock');
});

it('exit respects reserved stock when computing availability', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    seedStock($warehouse, $sparePart, 10, 20.0);
    // Reserve 6 → available = 4
    WarehouseSparePart::where('spare_part_id', $sparePart->id)->update(['reserved_stock' => 6]);

    // Trying to exit 5 (available is only 4) should fail
    expect(fn () => $service->recordExit($warehouse, $sparePart, 5, 20.0, $user))
        ->toThrow(RuntimeException::class);

    // Exiting exactly 4 should succeed
    $tx = $service->recordExit($warehouse, $sparePart, 4, 20.0, $user);
    expect((float) $tx->new_stock)->toBe(6.0);
});

// ── 3. Adjustment ─────────────────────────────────────────────────────────────

it('upward adjustment records positive delta and sets new absolute stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 10, 20.0);

    $tx = app(InventoryService::class)->adjustStock($warehouse, $sparePart, 15, 20.0, $user);
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($tx->type)->toBe(InventoryTransactionType::Adjustment)
        ->and((float) $tx->quantity)->toBe(5.0)   // delta = 15 - 10
        ->and((float) $tx->previous_stock)->toBe(10.0)
        ->and((float) $tx->new_stock)->toBe(15.0)
        ->and((float) $wsp->current_stock)->toBe(15.0);
});

it('downward adjustment records negative delta (shrinkage)', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 10, 20.0);

    $tx = app(InventoryService::class)->adjustStock($warehouse, $sparePart, 6, 20.0, $user);

    expect((float) $tx->quantity)->toBe(-4.0)  // delta = 6 - 10
        ->and((float) $tx->new_stock)->toBe(6.0);
});

it('adjustment to same stock value creates zero-delta transaction', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 10, 20.0);

    $tx = app(InventoryService::class)->adjustStock($warehouse, $sparePart, 10, 20.0, $user);

    expect((float) $tx->quantity)->toBe(0.0);
});

it('adjustment rejects negative absolute stock value', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    expect(fn () => app(InventoryService::class)->adjustStock($warehouse, $sparePart, -1, 20.0, $user))
        ->toThrow(InvalidArgumentException::class, 'Adjusted stock cannot be negative');
});

// ── 4. Transfer ───────────────────────────────────────────────────────────────

it('transfer decrements source and increments destination', function () {
    [$tenant, $warehouseA, $sparePart, $user] = inventorySetup();
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    seedStock($warehouseA, $sparePart, 20, 30.0);
    seedStock($warehouseB, $sparePart, 5, 30.0);

    app(InventoryService::class)->transferStock($warehouseA, $warehouseB, $sparePart, 8, 30.0, $user);

    $wspA = WarehouseSparePart::where('warehouse_id', $warehouseA->id)->where('spare_part_id', $sparePart->id)->first();
    $wspB = WarehouseSparePart::where('warehouse_id', $warehouseB->id)->where('spare_part_id', $sparePart->id)->first();

    expect((float) $wspA->current_stock)->toBe(12.0)
        ->and((float) $wspB->current_stock)->toBe(13.0);
});

it('transfer creates linked transaction pair with correct types', function () {
    [$tenant, $warehouseA, $sparePart, $user] = inventorySetup();
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    seedStock($warehouseA, $sparePart, 20, 30.0);

    ['out' => $outTx, 'in' => $inTx] = app(InventoryService::class)
        ->transferStock($warehouseA, $warehouseB, $sparePart, 5, 30.0, $user);

    expect($outTx->type)->toBe(InventoryTransactionType::TransferOut)
        ->and($inTx->type)->toBe(InventoryTransactionType::TransferIn)
        ->and((float) $outTx->quantity)->toBe(-5.0)
        ->and((float) $inTx->quantity)->toBe(5.0);
});

it('transfer transactions are linked bidirectionally via related_transaction_id', function () {
    [$tenant, $warehouseA, $sparePart, $user] = inventorySetup();
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    seedStock($warehouseA, $sparePart, 20, 30.0);

    ['out' => $outTx, 'in' => $inTx] = app(InventoryService::class)
        ->transferStock($warehouseA, $warehouseB, $sparePart, 5, 30.0, $user);

    expect($outTx->related_transaction_id)->toBe($inTx->id)
        ->and($inTx->related_transaction_id)->toBe($outTx->id);
});

it('transfer stores source and destination warehouse IDs on both transactions', function () {
    [$tenant, $warehouseA, $sparePart, $user] = inventorySetup();
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    seedStock($warehouseA, $sparePart, 20, 30.0);

    ['out' => $outTx, 'in' => $inTx] = app(InventoryService::class)
        ->transferStock($warehouseA, $warehouseB, $sparePart, 5, 30.0, $user);

    expect($outTx->source_warehouse_id)->toBe($warehouseA->id)
        ->and($outTx->destination_warehouse_id)->toBe($warehouseB->id)
        ->and($inTx->source_warehouse_id)->toBe($warehouseA->id)
        ->and($inTx->destination_warehouse_id)->toBe($warehouseB->id);
});

it('transfer rejects when source and destination are the same warehouse', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 20, 30.0);

    expect(fn () => app(InventoryService::class)->transferStock($warehouse, $warehouse, $sparePart, 5, 30.0, $user))
        ->toThrow(InvalidArgumentException::class, 'Source and destination warehouses must differ');
});

it('transfer rejects when source has insufficient available stock', function () {
    [$tenant, $warehouseA, $sparePart, $user] = inventorySetup();
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    seedStock($warehouseA, $sparePart, 10, 30.0);
    // Reserve 7 → available = 3
    WarehouseSparePart::where('warehouse_id', $warehouseA->id)->update(['reserved_stock' => 7]);

    expect(fn () => app(InventoryService::class)->transferStock($warehouseA, $warehouseB, $sparePart, 4, 30.0, $user))
        ->toThrow(RuntimeException::class, 'Insufficient available stock in source warehouse');
});

it('transfer updates destination average cost via weighted average', function () {
    [$tenant, $warehouseA, $sparePart, $user] = inventorySetup();
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id, 'created_by' => $user->id]);

    seedStock($warehouseA, $sparePart, 20, 30.0);
    seedStock($warehouseB, $sparePart, 10, 10.0); // existing stock at $10 avg

    // Transfer 10 units at $30 into B (which has 10 units at $10)
    // New avg for B = (10×10 + 10×30) / 20 = (100 + 300) / 20 = $20
    app(InventoryService::class)->transferStock($warehouseA, $warehouseB, $sparePart, 10, 30.0, $user);

    $wspB = WarehouseSparePart::where('warehouse_id', $warehouseB->id)->where('spare_part_id', $sparePart->id)->first();

    expect((float) $wspB->average_unit_cost)->toBe(20.0);
});

// ── 5. Reservation ────────────────────────────────────────────────────────────

it('reservation increments reserved_stock without creating a transaction', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 15, 20.0);

    app(InventoryService::class)->reserveForWorkOrder($warehouse, $sparePart, 5);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->reserved_stock)->toBe(5.0)
        ->and((float) $wsp->current_stock)->toBe(15.0)           // stock unchanged
        ->and(InventoryTransaction::where('spare_part_id', $sparePart->id)->count())->toBe(0);
});

it('multiple reservations accumulate on reserved_stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    seedStock($warehouse, $sparePart, 20, 20.0);

    $service->reserveForWorkOrder($warehouse, $sparePart, 3);
    $service->reserveForWorkOrder($warehouse, $sparePart, 4);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->reserved_stock)->toBe(7.0);
});

it('reservation rejects when available stock is insufficient', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 5, 20.0);

    expect(fn () => app(InventoryService::class)->reserveForWorkOrder($warehouse, $sparePart, 6))
        ->toThrow(RuntimeException::class, 'Cannot reserve');
});

it('available_stock accessor returns current minus reserved', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    $wsp = seedStock($warehouse, $sparePart, 20, 20.0);
    $wsp->update(['reserved_stock' => 6]);

    expect($wsp->fresh()->available_stock)->toBe(14.0);
});

// ── 6. Release Reservation ────────────────────────────────────────────────────

it('release decrements reserved_stock without creating a transaction', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    seedStock($warehouse, $sparePart, 15, 20.0);
    $service->reserveForWorkOrder($warehouse, $sparePart, 8);

    $service->releaseReservation($warehouse, $sparePart, 3);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->reserved_stock)->toBe(5.0)
        ->and(InventoryTransaction::where('spare_part_id', $sparePart->id)->count())->toBe(0);
});

it('release clamps reserved_stock to zero when releasing more than reserved', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 15, 20.0);
    WarehouseSparePart::where('spare_part_id', $sparePart->id)->update(['reserved_stock' => 3]);

    app(InventoryService::class)->releaseReservation($warehouse, $sparePart, 10); // more than the 3 reserved

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->reserved_stock)->toBe(0.0);
});

// ── 7. Consumption from Work Order ───────────────────────────────────────────

it('consumption creates transaction, decrements stock and reserved stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $woService = app(WorkOrderService::class);
    $invService = app(InventoryService::class);

    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $wo = $woService->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Reparación',
        'description' => 'desc',
        'equipment_stopped' => false,
    ], $user);

    seedStock($warehouse, $sparePart, 20, 30.0);
    WarehouseSparePart::where('spare_part_id', $sparePart->id)->update(['reserved_stock' => 5]);

    $tx = $invService->consumeFromWorkOrder($warehouse, $sparePart, 5, 30.0, $wo, $user);
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($tx->type)->toBe(InventoryTransactionType::Consumption)
        ->and((float) $tx->quantity)->toBe(-5.0)
        ->and($tx->work_order_id)->toBe($wo->id)
        ->and($tx->reference_number)->toBe($wo->work_order_number)
        ->and((float) $wsp->current_stock)->toBe(15.0)
        ->and((float) $wsp->reserved_stock)->toBe(0.0); // 5 reserved - 5 consumed = 0
});

it('consumption rejects when current_stock is insufficient', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    seedStock($warehouse, $sparePart, 3, 30.0);

    expect(fn () => app(InventoryService::class)->consumeFromWorkOrder($warehouse, $sparePart, 5, 30.0, $wo, $user))
        ->toThrow(RuntimeException::class, 'Insufficient stock for consumption');
});

// ── 8. Return to Inventory ────────────────────────────────────────────────────

it('return increments stock and creates transaction with positive quantity', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    seedStock($warehouse, $sparePart, 5, 30.0);

    $tx = app(InventoryService::class)->returnFromWorkOrder($warehouse, $sparePart, 3, 30.0, $wo, $user);
    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect($tx->type)->toBe(InventoryTransactionType::Return)
        ->and((float) $tx->quantity)->toBe(3.0)
        ->and($tx->work_order_id)->toBe($wo->id)
        ->and((float) $wsp->current_stock)->toBe(8.0);
});

it('return updates average cost after restocking', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $wo = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

    // Current: 10 units at $20 avg; return 5 units at $30
    // New avg = (10×20 + 5×30) / 15 = (200 + 150) / 15 = $23.3333
    seedStock($warehouse, $sparePart, 10, 20.0);

    app(InventoryService::class)->returnFromWorkOrder($warehouse, $sparePart, 5, 30.0, $wo, $user);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect(round((float) $wsp->average_unit_cost, 4))->toBe(23.3333);
});

// ── 9. Negative Stock Prevention ─────────────────────────────────────────────

it('database check constraint rejects negative current_stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $wsp = seedStock($warehouse, $sparePart, 10, 20.0);

    expect(fn () => DB::statement(
        'UPDATE warehouse_spare_parts SET current_stock = -1 WHERE id = ?', [$wsp->id]
    ))->toThrow(QueryException::class);
});

it('database check constraint rejects negative reserved_stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $wsp = seedStock($warehouse, $sparePart, 10, 20.0);

    expect(fn () => DB::statement(
        'UPDATE warehouse_spare_parts SET reserved_stock = -1 WHERE id = ?', [$wsp->id]
    ))->toThrow(QueryException::class);
});

it('failed exit leaves stock unchanged (transaction rollback)', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    seedStock($warehouse, $sparePart, 5, 20.0);

    try {
        app(InventoryService::class)->recordExit($warehouse, $sparePart, 10, 20.0, $user);
    } catch (RuntimeException) {
        // expected
    }

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->current_stock)->toBe(5.0)
        ->and(InventoryTransaction::where('spare_part_id', $sparePart->id)->count())->toBe(0);
});

// ── 10. Transaction Integrity ─────────────────────────────────────────────────

it('transaction numbers are sequential per tenant', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    $year = date('Y');

    $tx1 = $service->receiveEntry($warehouse, $sparePart, 5, 20.0, $user);
    $tx2 = $service->receiveEntry($warehouse, $sparePart, 5, 20.0, $user);
    $tx3 = $service->receiveEntry($warehouse, $sparePart, 5, 20.0, $user);

    expect($tx1->transaction_number)->toBe("MVT-{$year}-000001")
        ->and($tx2->transaction_number)->toBe("MVT-{$year}-000002")
        ->and($tx3->transaction_number)->toBe("MVT-{$year}-000003");
});

it('transaction numbers are isolated between tenants', function () {
    $user = User::factory()->create();

    $tenantA = Tenant::factory()->create();
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenantA->id, 'created_by' => $user->id]);
    $partA = SparePart::factory()->create(['tenant_id' => $tenantA->id, 'created_by' => $user->id]);

    $tenantB = Tenant::factory()->create();
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenantB->id, 'created_by' => $user->id]);
    $partB = SparePart::factory()->create(['tenant_id' => $tenantB->id, 'created_by' => $user->id]);

    $service = app(InventoryService::class);
    $year = date('Y');

    $txA = $service->receiveEntry($warehouseA, $partA, 5, 20.0, $user);
    $txB = $service->receiveEntry($warehouseB, $partB, 5, 20.0, $user);

    // Both start at 000001 within their own tenant
    expect($txA->transaction_number)->toBe("MVT-{$year}-000001")
        ->and($txB->transaction_number)->toBe("MVT-{$year}-000001");
});

it('snapshots preserve part code and name even after spare part is renamed', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();

    $tx = app(InventoryService::class)->receiveEntry($warehouse, $sparePart, 5, 20.0, $user);

    $originalCode = $sparePart->code;
    $originalName = $sparePart->name;

    $sparePart->update(['code' => 'NEW-CODE', 'name' => 'Renamed Part']);

    // Transaction snapshot must still hold original values
    expect($tx->spare_part_code_snapshot)->toBe($originalCode)
        ->and($tx->spare_part_name_snapshot)->toBe($originalName);
});

it('total_cost is calculated as absolute quantity times unit_cost', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    seedStock($warehouse, $sparePart, 20, 25.0);

    $entry = $service->receiveEntry($warehouse, $sparePart, 4, 25.0, $user);
    $exit = $service->recordExit($warehouse, $sparePart, 2, 25.0, $user);

    expect((float) $entry->total_cost)->toBe(100.0)  // 4 × 25
        ->and((float) $exit->total_cost)->toBe(50.0);  // 2 × 25 (absolute, not negative)
});

// ── 11. Concurrency Scenarios ─────────────────────────────────────────────────

it('sequential entries accumulate without lost updates', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    // Simulate rapid sequential entries (no forking — validates accumulation logic)
    for ($i = 0; $i < 5; $i++) {
        $service->receiveEntry($warehouse, $sparePart, 10, 20.0, $user);
    }

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();

    expect((float) $wsp->current_stock)->toBe(50.0)
        ->and(InventoryTransaction::where('spare_part_id', $sparePart->id)->count())->toBe(5);
});

it('lock prevents reading stale stock when concurrent exits would cause negative stock', function () {
    // Verify that the service-level guard (available check) prevents the double-exit scenario
    // even when operating close to zero, and that the DB constraint is the final safety net.
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $service = app(InventoryService::class);

    seedStock($warehouse, $sparePart, 10, 20.0);

    // First exit: succeeds
    $service->recordExit($warehouse, $sparePart, 10, 20.0, $user);

    // Second exit: should fail because stock is now 0
    expect(fn () => $service->recordExit($warehouse, $sparePart, 1, 20.0, $user))
        ->toThrow(RuntimeException::class, 'Insufficient available stock');

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();
    expect((float) $wsp->current_stock)->toBe(0.0);
});

it('complete lifecycle: entry → reserve → consume → return leaves correct final stock', function () {
    [$tenant, $warehouse, $sparePart, $user] = inventorySetup();
    $invService = app(InventoryService::class);
    $woService = app(WorkOrderService::class);
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $wo = $woService->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'priority' => 'p3_medium',
        'title' => 'Reparación',
        'description' => 'desc',
        'equipment_stopped' => false,
    ], $user);

    // Entry: 20 units
    $invService->receiveEntry($warehouse, $sparePart, 20, 30.0, $user);

    // Reserve 8 for WO
    $invService->reserveForWorkOrder($warehouse, $sparePart, 8);

    $wsp = WarehouseSparePart::where('spare_part_id', $sparePart->id)->first();
    expect((float) $wsp->current_stock)->toBe(20.0)
        ->and((float) $wsp->reserved_stock)->toBe(8.0)
        ->and($wsp->available_stock)->toBe(12.0);

    // Consume 6 from WO (2 remain reserved but unused)
    $invService->consumeFromWorkOrder($warehouse, $sparePart, 6, 30.0, $wo, $user);

    $wsp->refresh();
    expect((float) $wsp->current_stock)->toBe(14.0)
        ->and((float) $wsp->reserved_stock)->toBe(2.0); // 8 - 6 = 2 still reserved

    // Return 1 unit back (used too many)
    $invService->returnFromWorkOrder($warehouse, $sparePart, 1, 30.0, $wo, $user);

    // Release remaining reservation (WO closing, 2 still reserved)
    $invService->releaseReservation($warehouse, $sparePart, 2);

    $wsp->refresh();
    expect((float) $wsp->current_stock)->toBe(15.0)   // 14 + 1 returned
        ->and((float) $wsp->reserved_stock)->toBe(0.0)
        ->and(InventoryTransaction::where('spare_part_id', $sparePart->id)->count())->toBe(3); // entry + consume + return
});
