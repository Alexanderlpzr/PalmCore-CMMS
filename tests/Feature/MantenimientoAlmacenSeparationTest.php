<?php

use App\Filament\Resources\Inventory\PurchaseOrder\PurchaseOrderResource;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use App\Filament\Resources\Inventory\Warehouse\WarehouseResource;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\PartsRelationManager;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Spatie\Permission\PermissionRegistrar;

/*
 * Mantenimiento y almacén trabajan aparte (pedido de El Pajuil, 2026-10-18): el rol
 * «mantenimiento» no ve el inventario ni administra usuarios; el de almacén solo ve el
 * inventario. Proveedores y fabricantes son de los dos, y las OT siguen sacando
 * repuestos del almacén.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function actuarConRol(string $role, Tenant $tenant): User
{
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $user->tenants()->attach($tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    $user = $user->fresh();

    test()->actingAs($user);
    Filament::setTenant($tenant);

    return $user;
}

/** @return list<string> «Grupo / Pantalla» de lo que el usuario actual puede abrir */
function pantallasVisibles(): array
{
    $panel = Filament::getPanel('admin');

    return collect([...$panel->getResources(), ...$panel->getPages()])
        ->filter(fn (string $screen): bool => $screen::canAccess())
        ->map(fn (string $screen): string => (string) ($screen::getNavigationGroup() ?? 'Sin grupo').' / '.class_basename($screen))
        ->values()
        ->all();
}

it('keeps maintenance away from inventory, payroll and user management', function (): void {
    actuarConRol('mantenimiento', $this->tenant);

    $pantallas = pantallasVisibles();
    $prohibidas = array_values(array_filter($pantallas, fn (string $p): bool => str_starts_with($p, 'Inventario')
        || str_starts_with($p, 'Talento Humano')
        || str_ends_with($p, '/ UserResource')
        || str_ends_with($p, '/ RoleResource')));

    expect($prohibidas)->toBe([])
        ->and(SparePartResource::canAccess())->toBeFalse()
        ->and(WarehouseResource::canAccess())->toBeFalse()
        ->and(PurchaseOrderResource::canAccess())->toBeFalse()
        // Lo suyo, y lo que comparte con el almacén.
        ->and($pantallas)->toContain('Indicadores / Dashboard')
        ->and($pantallas)->toContain('Gestión de Activos / SupplierResource')
        ->and($pantallas)->toContain('Gestión de Activos / ManufacturerResource');
});

it('lets work orders keep taking spare parts from the warehouse', function (): void {
    actuarConRol('mantenimiento', $this->tenant);
    $orden = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(PartsRelationManager::canViewForRecord($orden, ViewWorkOrder::class))->toBeTrue()
        ->and(auth()->user()->can('work-order-parts.create'))->toBeTrue();
});

it('shows the warehouse account only the inventory and what it shares', function (): void {
    actuarConRol('almacen', $this->tenant);

    $ajenas = array_values(array_filter(pantallasVisibles(), fn (string $p): bool => ! str_starts_with($p, 'Inventario')
        && ! in_array($p, ['Sin grupo / Inicio', 'Gestión de Activos / SupplierResource', 'Gestión de Activos / ManufacturerResource'], true)));

    expect($ajenas)->toBe([])
        ->and(SparePartResource::canAccess())->toBeTrue();
});

it('lists the warehouse screens in the menu of the warehouse account', function (): void {
    actuarConRol('almacen', $this->tenant);

    expect(SparePartResource::shouldRegisterNavigation())->toBeTrue()
        ->and(WarehouseResource::shouldRegisterNavigation())->toBeTrue()
        ->and(PurchaseOrderResource::shouldRegisterNavigation())->toBeTrue()
        ->and(SupplierResource::shouldRegisterNavigation())->toBeTrue()
        ->and(ManufacturerResource::shouldRegisterNavigation())->toBeTrue();
});

it('keeps the warehouse screens out of the maintenance menu', function (): void {
    actuarConRol('mantenimiento', $this->tenant);

    expect(SparePartResource::shouldRegisterNavigation())->toBeFalse()
        ->and(WarehouseResource::shouldRegisterNavigation())->toBeFalse()
        ->and(PurchaseOrderResource::shouldRegisterNavigation())->toBeFalse()
        ->and(SupplierResource::shouldRegisterNavigation())->toBeTrue();
});
