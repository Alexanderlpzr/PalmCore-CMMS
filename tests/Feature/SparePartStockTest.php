<?php

use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use Illuminate\Support\Facades\DB;

// ── totalStock() ──────────────────────────────────────────────────────────────

it('totalStock returns the pre-loaded withSum attribute without hitting the database', function () {
    $part = new SparePart;
    $part->setAttribute('warehouse_stock_sum_current_stock', 42.5);

    DB::enableQueryLog();
    $result = $part->totalStock();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($result)->toBe(42.5)
        ->and($queries)->toBeEmpty();
});

it('totalStock treats a null pre-loaded attribute as zero stock', function () {
    $part = new SparePart;
    $part->setAttribute('warehouse_stock_sum_current_stock', null);

    expect($part->totalStock())->toBe(0.0);
});

it('totalStock queries the database when the aggregate attribute is absent', function () {
    $tenant = Tenant::factory()->create();
    $warehouseA = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $warehouseB = Warehouse::factory()->create(['tenant_id' => $tenant->id]);
    $part = SparePart::factory()->create(['tenant_id' => $tenant->id]);

    WarehouseSparePart::factory()->withStock(15)->create([
        'tenant_id' => $tenant->id,
        'warehouse_id' => $warehouseA->id,
        'spare_part_id' => $part->id,
    ]);
    WarehouseSparePart::factory()->withStock(10)->create([
        'tenant_id' => $tenant->id,
        'warehouse_id' => $warehouseB->id,
        'spare_part_id' => $part->id,
    ]);

    expect($part->totalStock())->toBe(25.0);
});

// ── isBelowMinimumStock() ─────────────────────────────────────────────────────

it('isBelowMinimumStock returns false when minimum_stock is null', function () {
    $part = new SparePart(['minimum_stock' => null]);
    $part->setAttribute('warehouse_stock_sum_current_stock', 0);

    expect($part->isBelowMinimumStock())->toBeFalse();
});

it('isBelowMinimumStock returns true when stock is below minimum', function () {
    $part = new SparePart(['minimum_stock' => 10]);
    $part->setAttribute('warehouse_stock_sum_current_stock', 5);

    expect($part->isBelowMinimumStock())->toBeTrue();
});

it('isBelowMinimumStock returns false when stock meets the minimum exactly', function () {
    $part = new SparePart(['minimum_stock' => 10]);
    $part->setAttribute('warehouse_stock_sum_current_stock', 10);

    expect($part->isBelowMinimumStock())->toBeFalse();
});

// ── isBelowReorderPoint() ─────────────────────────────────────────────────────

it('isBelowReorderPoint returns false when reorder_point is null', function () {
    $part = new SparePart(['reorder_point' => null]);
    $part->setAttribute('warehouse_stock_sum_current_stock', 0);

    expect($part->isBelowReorderPoint())->toBeFalse();
});

it('isBelowReorderPoint returns true when stock is below the reorder point', function () {
    $part = new SparePart(['reorder_point' => 8]);
    $part->setAttribute('warehouse_stock_sum_current_stock', 3);

    expect($part->isBelowReorderPoint())->toBeTrue();
});

it('isBelowReorderPoint returns false when stock is above the reorder point', function () {
    $part = new SparePart(['reorder_point' => 8]);
    $part->setAttribute('warehouse_stock_sum_current_stock', 20);

    expect($part->isBelowReorderPoint())->toBeFalse();
});

// ── N+1 elimination ───────────────────────────────────────────────────────────

it('filtering a withSum-loaded collection fires no additional stock queries', function () {
    $tenant = Tenant::factory()->create();
    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenant->id]);

    foreach (range(1, 3) as $_) {
        $part = SparePart::factory()->create([
            'tenant_id' => $tenant->id,
            'reorder_point' => 10,
            'is_active' => true,
        ]);
        WarehouseSparePart::factory()->withStock(3)->create([
            'tenant_id' => $tenant->id,
            'warehouse_id' => $warehouse->id,
            'spare_part_id' => $part->id,
        ]);
    }

    $parts = SparePart::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->whereNotNull('reorder_point')
        ->withSum(['warehouseStock' => fn ($q) => $q->withoutGlobalScopes()], 'current_stock')
        ->get();

    DB::enableQueryLog();
    $below = $parts->filter(fn (SparePart $sp) => $sp->isBelowReorderPoint());
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($below)->toHaveCount(3)
        ->and($queries)->toBeEmpty();
});
