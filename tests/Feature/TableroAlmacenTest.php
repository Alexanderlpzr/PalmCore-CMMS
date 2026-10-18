<?php

use App\Domain\Inventory\Services\WarehouseDashboard;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\TableroAlmacen;
use App\Filament\Pages\TableroTalentoHumano;
use App\Filament\Widgets\Inventory\MonthlyOutgoingValueChart;
use App\Filament\Widgets\Inventory\MostUsedSparePartsChart;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\SparePart;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseSparePart;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El tablero de Almacén: lo que hay que reponer, lo que está por llegar, el valor del
 * inventario, y los tres movimientos del día —entrada, salida y conteo— como botones.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $this->almacen = Warehouse::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Almacén principal']);

    // En el mínimo: hay 3 y el mínimo es 5. Sin existencias: ninguno en el estante.
    $this->rodamiento = SparePart::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rodamiento 6205', 'minimum_stock' => 5, 'unit_cost' => 20_000]);
    WarehouseSparePart::factory()->create([
        'tenant_id' => $this->tenant->id, 'warehouse_id' => $this->almacen->id, 'spare_part_id' => $this->rodamiento->id,
        'current_stock' => 3, 'average_unit_cost' => 20_000,
    ]);
    $this->correa = SparePart::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Correa B-52', 'minimum_stock' => 0, 'unit_cost' => 50_000]);

    $this->bodeguero = User::factory()->create(['is_active' => true]);
    $this->bodeguero->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->bodeguero->assignRole('almacen');
});

it('counts what needs restocking and what the inventory is worth', function (): void {
    $atencion = app(WarehouseDashboard::class)->attention($this->tenant);

    expect($atencion['belowMinimum'])->toBe(1)
        ->and($atencion['outOfStock'])->toBe(1)
        ->and($atencion['inventoryValue'])->toBe(60_000.0)
        ->and($atencion['openPurchaseOrders'])->toBe(0);
});

it('lets the warehouse account register an entry, an exit and a count from the dashboard', function (): void {
    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $pantalla = Livewire::test(TableroAlmacen::class)
        ->assertOk()
        ->assertSee('Bajo el mínimo')
        ->assertSee('Sin existencias')
        ->assertSee('Valor del inventario');

    $pantalla->callAction('entrada', [
        'warehouse_id' => $this->almacen->id, 'spare_part_id' => $this->correa->id,
        'quantity' => 4, 'unit_cost' => 50_000, 'reference' => 'FAC-123',
    ])->assertNotified('Movimiento registrado');

    // Salir más de lo que hay no se registra.
    $pantalla->callAction('salida', [
        'warehouse_id' => $this->almacen->id, 'spare_part_id' => $this->correa->id, 'quantity' => 10, 'exit_reason' => 'damage_or_loss',
    ])->assertNotified('No hay suficiente existencia disponible para esa salida.');

    // El formulario rechazado queda abierto para corregir la cantidad; se empieza de nuevo.
    $pantalla = Livewire::test(TableroAlmacen::class);

    $pantalla->callAction('salida', [
        'warehouse_id' => $this->almacen->id, 'spare_part_id' => $this->correa->id, 'quantity' => 1, 'exit_reason' => 'damage_or_loss', 'reference' => 'Daño',
    ])->assertNotified('Movimiento registrado');

    // El conteo deja lo que hay en el estante.
    $pantalla->callAction('conteo', [
        'warehouse_id' => $this->almacen->id, 'spare_part_id' => $this->rodamiento->id, 'quantity' => 2,
    ])->assertNotified('Movimiento registrado');

    $existencia = fn (SparePart $part): float => (float) WarehouseSparePart::query()->where('spare_part_id', $part->id)->value('current_stock');

    expect($existencia($this->correa))->toBe(3.0)
        ->and($existencia($this->rodamiento))->toBe(2.0)
        ->and(app(WarehouseDashboard::class)->mostUsed($this->tenant))->toBe(['Correa B-52' => 1.0]);

    Livewire::test(MonthlyOutgoingValueChart::class)->assertOk();
    Livewire::test(MostUsedSparePartsChart::class)->assertOk();
});

it('opens the panel on the warehouse dashboard for the warehouse account, and nowhere else', function (): void {
    $this->actingAs($this->bodeguero);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    Livewire::test(Inicio::class)->assertRedirect(TableroAlmacen::getUrl());

    expect(TableroAlmacen::canAccess())->toBeTrue()
        ->and(TableroTalentoHumano::canAccess())->toBeFalse();

    foreach (['talento-humano', 'porteria'] as $rol) {
        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $usuario->assignRole($rol);
        $this->actingAs($usuario);

        expect(TableroAlmacen::canAccess())->toBeFalse();
    }
});
