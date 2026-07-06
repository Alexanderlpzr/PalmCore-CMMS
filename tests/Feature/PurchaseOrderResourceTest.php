<?php

use App\Domain\Inventory\Enums\PurchaseOrderStatus;
use App\Domain\Inventory\Services\PurchaseOrderService;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\CreatePurchaseOrder;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\ListPurchaseOrders;
use App\Filament\Resources\Inventory\PurchaseOrder\Pages\ViewPurchaseOrder;
use App\Models\PurchaseOrder;
use App\Models\SparePart;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
    $this->supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('lists purchase orders for the tenant', function () {
    PurchaseOrder::factory()->create([
        'tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id,
        'supplier_id' => $this->supplier->id, 'po_number' => 'OC-2026-000123',
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->assertOk()
        ->assertSee('OC-2026-000123');
});

it('creates a purchase order with lines through the form', function () {
    $part = SparePart::factory()->create(['tenant_id' => $this->tenant->id, 'unit_cost' => 12]);

    Livewire::test(CreatePurchaseOrder::class)
        ->fillForm([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'lines' => [
                ['spare_part_id' => $part->id, 'quantity_ordered' => 5, 'unit_cost' => 12],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $po = PurchaseOrder::where('tenant_id', $this->tenant->id)->firstOrFail();

    expect($po->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($po->lines()->count())->toBe(1)
        ->and((float) $po->total)->toBe(60.0);
});

it('generates draft orders from the reorder header action', function () {
    // A part below reorder point with a supplier
    SparePart::factory()->create([
        'tenant_id' => $this->tenant->id, 'supplier_id' => $this->supplier->id,
        'reorder_point' => 10, 'reorder_quantity' => 15, 'unit_cost' => 4, 'is_active' => true,
    ]);

    Livewire::test(ListPurchaseOrders::class)
        ->callAction('generateFromReorder');

    expect(PurchaseOrder::where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('sends a draft order from the view page action', function () {
    $part = SparePart::factory()->create(['tenant_id' => $this->tenant->id]);
    $po = app(PurchaseOrderService::class)->create(
        ['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->warehouse->id, 'supplier_id' => $this->supplier->id],
        [['spare_part_id' => $part->id, 'quantity_ordered' => 3, 'unit_cost' => 7]],
        $this->admin,
    );

    Livewire::test(ViewPurchaseOrder::class, ['record' => $po->id])
        ->callAction('send');

    expect($po->refresh()->status)->toBe(PurchaseOrderStatus::Sent);
});
