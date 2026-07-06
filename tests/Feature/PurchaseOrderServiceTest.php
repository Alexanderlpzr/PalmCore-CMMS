<?php

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Services\PurchaseOrderService;
use App\Models\PurchaseOrder;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;

function poContext(): array
{
    $tenant = Tenant::factory()->create();

    return [
        'tenant' => $tenant,
        'user' => User::factory()->create(),
        'supplier' => Supplier::factory()->create(['tenant_id' => $tenant->id]),
        'warehouse' => Warehouse::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]),
    ];
}

function draftPo(array $ctx, array $lines): PurchaseOrder
{
    return app(PurchaseOrderService::class)->create(
        ['tenant_id' => $ctx['tenant']->id, 'warehouse_id' => $ctx['warehouse']->id, 'supplier_id' => $ctx['supplier']->id],
        $lines,
        $ctx['user'],
    );
}

// ── Create ──────────────────────────────────────────────────────────────────

it('creates a draft PO with lines and a computed total', function () {
    $ctx = poContext();
    $p1 = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $p2 = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);

    $po = draftPo($ctx, [
        ['spare_part_id' => $p1->id, 'quantity_ordered' => 10, 'unit_cost' => 5],   // 50
        ['spare_part_id' => $p2->id, 'quantity_ordered' => 4, 'unit_cost' => 25],   // 100
    ]);

    expect($po->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($po->lines()->count())->toBe(2)
        ->and((float) $po->total)->toBe(150.0)
        ->and($po->po_number)->toStartWith('OC-'.date('Y'));
});

it('numbers purchase orders sequentially per tenant', function () {
    $ctx = poContext();
    $part = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $line = [['spare_part_id' => $part->id, 'quantity_ordered' => 1, 'unit_cost' => 1]];

    $first = draftPo($ctx, $line);
    $second = draftPo($ctx, $line);

    expect($first->po_number)->toBe('OC-'.date('Y').'-000001')
        ->and($second->po_number)->toBe('OC-'.date('Y').'-000002');
});

// ── Send ────────────────────────────────────────────────────────────────────

it('sends a draft order and stamps ordered_at', function () {
    $ctx = poContext();
    $part = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $po = draftPo($ctx, [['spare_part_id' => $part->id, 'quantity_ordered' => 2, 'unit_cost' => 10]]);

    $po = app(PurchaseOrderService::class)->send($po);

    expect($po->status)->toBe(PurchaseOrderStatus::Sent)
        ->and($po->ordered_at)->not->toBeNull();
});

it('refuses to send an order with no lines', function () {
    $ctx = poContext();
    $po = draftPo($ctx, []);

    expect(fn () => app(PurchaseOrderService::class)->send($po))
        ->toThrow(RuntimeException::class);
});

// ── Receiving ───────────────────────────────────────────────────────────────

it('receives a line into warehouse stock and advances the order status', function () {
    $ctx = poContext();
    $part = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $service = app(PurchaseOrderService::class);

    $po = $service->send(draftPo($ctx, [['spare_part_id' => $part->id, 'quantity_ordered' => 10, 'unit_cost' => 5]]));
    $line = $po->lines()->first();

    // Partial receipt
    $service->receiveLine($line, 4, $ctx['user']);

    $stock = fn () => (float) WarehouseSparePart::where('warehouse_id', $ctx['warehouse']->id)
        ->where('spare_part_id', $part->id)->value('current_stock');

    expect((float) $line->refresh()->quantity_received)->toBe(4.0)
        ->and($stock())->toBe(4.0)
        ->and($po->refresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);

    // Remaining receipt closes the order
    $service->receiveLine($line->refresh(), 6, $ctx['user']);

    expect($stock())->toBe(10.0)
        ->and($po->refresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($po->received_at)->not->toBeNull();
});

it('clamps a receipt to the pending quantity', function () {
    $ctx = poContext();
    $part = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $service = app(PurchaseOrderService::class);

    $po = $service->send(draftPo($ctx, [['spare_part_id' => $part->id, 'quantity_ordered' => 8, 'unit_cost' => 2]]));
    $line = $po->lines()->first();

    $service->receiveLine($line, 999, $ctx['user']); // asks for more than ordered

    expect((float) $line->refresh()->quantity_received)->toBe(8.0)
        ->and($po->refresh()->status)->toBe(PurchaseOrderStatus::Received);
});

it('receiveAll books every pending line and closes the order', function () {
    $ctx = poContext();
    $p1 = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $p2 = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $service = app(PurchaseOrderService::class);

    $po = $service->send(draftPo($ctx, [
        ['spare_part_id' => $p1->id, 'quantity_ordered' => 3, 'unit_cost' => 4],
        ['spare_part_id' => $p2->id, 'quantity_ordered' => 5, 'unit_cost' => 4],
    ]));

    $service->receiveAll($po, $ctx['user']);

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and((float) WarehouseSparePart::where('spare_part_id', $p1->id)->value('current_stock'))->toBe(3.0)
        ->and((float) WarehouseSparePart::where('spare_part_id', $p2->id)->value('current_stock'))->toBe(5.0);
});

it('cannot receive against a draft (unsent) order', function () {
    $ctx = poContext();
    $part = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $po = draftPo($ctx, [['spare_part_id' => $part->id, 'quantity_ordered' => 2, 'unit_cost' => 1]]);

    expect(fn () => app(PurchaseOrderService::class)->receiveLine($po->lines()->first(), 1, $ctx['user']))
        ->toThrow(RuntimeException::class);
});

// ── Cancel ──────────────────────────────────────────────────────────────────

it('cancels an open order but not a received one', function () {
    $ctx = poContext();
    $part = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $service = app(PurchaseOrderService::class);

    $po = $service->cancel(draftPo($ctx, [['spare_part_id' => $part->id, 'quantity_ordered' => 1, 'unit_cost' => 1]]));
    expect($po->status)->toBe(PurchaseOrderStatus::Cancelled);

    $received = $service->send(draftPo($ctx, [['spare_part_id' => $part->id, 'quantity_ordered' => 1, 'unit_cost' => 1]]));
    $service->receiveAll($received, $ctx['user']);

    expect(fn () => $service->cancel($received->refresh()))->toThrow(RuntimeException::class);
});

// ── Reorder assistant ─────────────────────────────────────────────────────────

it('generates draft POs from parts below their reorder point, grouped by supplier', function () {
    $ctx = poContext();
    $supplierA = Supplier::factory()->create(['tenant_id' => $ctx['tenant']->id]);

    // Below reorder (no stock, reorder_point 10) → should be ordered, qty 20
    $low = SparePart::factory()->create([
        'tenant_id' => $ctx['tenant']->id, 'supplier_id' => $supplierA->id,
        'reorder_point' => 10, 'reorder_quantity' => 20, 'unit_cost' => 3, 'is_active' => true,
    ]);

    // Well stocked (50 ≥ reorder_point 5) → should be ignored
    $stocked = SparePart::factory()->create(['tenant_id' => $ctx['tenant']->id, 'reorder_point' => 5, 'is_active' => true]);
    WarehouseSparePart::create([
        'tenant_id' => $ctx['tenant']->id, 'warehouse_id' => $ctx['warehouse']->id,
        'spare_part_id' => $stocked->id, 'current_stock' => 50, 'average_unit_cost' => 1,
    ]);

    $created = app(PurchaseOrderService::class)->generateFromReorder($ctx['tenant']->id, $ctx['user']);

    expect($created)->toHaveCount(1);

    $po = $created->first();
    expect($po->supplier_id)->toBe($supplierA->id)
        ->and($po->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($po->lines()->count())->toBe(1)
        ->and((float) $po->lines()->first()->quantity_ordered)->toBe(20.0)
        ->and($po->lines()->first()->spare_part_id)->toBe($low->id);
});
