<?php

use App\Filament\Pages\CuadroCruce;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EficienciaDePlanta;
use App\Filament\Pages\Energia;
use App\Filament\Pages\IndicadoresDeParos;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\ProductividadYEficiencia;
use App\Filament\Pages\TableroDireccion;
use App\Filament\Pages\TableroSst;
use App\Filament\Pages\TanquesAceite;
use App\Filament\Resources\Commercial\KernelInventoryCount\KernelInventoryCountResource;
use App\Filament\Resources\Commercial\KernelInventoryCount\Pages\ManageKernelInventoryCounts;
use App\Filament\Resources\Commercial\KernelTollLoad\KernelTollLoadResource;
use App\Filament\Resources\Commercial\KernelTollLoad\Pages\ManageKernelTollLoads;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Filament\Resources\Downtime\DowntimeEventResource;
use App\Filament\Resources\Safety\SstFindingResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\EnergyMeter;
use App\Models\KernelTollLoad;
use App\Models\OilTank;
use App\Models\OilTankReading;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * La Dirección de Producción solo consulta (2026-11-19): ve los tanques, la maquila, el
 * inventario de palmiste, el Dashboard con paros y energía, y los hallazgos. No mide, no
 * registra ni corrige nada, y no ve ventas ni cartera.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $this->direccion = User::factory()->create(['is_active' => true]);
    $this->direccion->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->direccion->assignRole('direccion-produccion');

    $this->actingAs($this->direccion);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('sees production, the dashboard and the findings, read only', function (): void {
    $tanque = OilTank::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Tanque 1']);
    KernelTollLoad::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(Role::humanizeName('direccion-produccion'))->toBe('Dirección de Producción')
        ->and(TanquesAceite::canAccess())->toBeTrue()
        ->and(KernelTollLoadResource::canViewAny())->toBeTrue()
        ->and(KernelInventoryCountResource::canViewAny())->toBeTrue()
        ->and(DowntimeEventResource::canViewAny())->toBeTrue()
        ->and(Energia::canAccess())->toBeTrue()
        ->and(Dashboard::canAccess())->toBeTrue()
        ->and(IndicadoresDeParos::canAccess())->toBeTrue()
        ->and(EficienciaDePlanta::canAccess())->toBeTrue()
        ->and(ProductividadYEficiencia::canAccess())->toBeTrue()
        ->and(TableroSst::canAccess())->toBeTrue()
        ->and(SstFindingResource::canViewAny())->toBeTrue();

    // Nada que guardar, medir, registrar ni corregir.
    expect($this->direccion->can('record', OilTank::class))->toBeFalse()
        ->and(KernelTollLoadResource::canCreate())->toBeFalse()
        ->and(KernelInventoryCountResource::canCreate())->toBeFalse()
        ->and(DowntimeEventResource::canCreate())->toBeFalse()
        ->and(SstFindingResource::canCreate())->toBeFalse()
        ->and($this->direccion->can('create', EnergyMeter::class))->toBeFalse();

    Livewire::test(TanquesAceite::class)
        ->assertOk()
        ->assertDontSee('Guardar medición')
        ->set("levels.{$tanque->id}.level", '400')
        ->call('guardar');
    expect(OilTankReading::query()->exists())->toBeFalse();

    Livewire::test(ManageKernelTollLoads::class)->assertOk()->assertActionHidden('create');
    Livewire::test(ManageKernelInventoryCounts::class)->assertOk()->assertActionHidden('create');
    Livewire::test(Dashboard::class)->assertOk();
    Livewire::test(ProductividadYEficiencia::class)->assertOk()->assertActionHidden('cargarProduccion');

    // Ni ventas, ni el cuadro cruce, ni el tablero de Dirección.
    expect(SalesDispatchResource::canViewAny())->toBeFalse()
        ->and(CuadroCruce::canAccess())->toBeFalse()
        ->and(TableroDireccion::canAccess())->toBeFalse();
});

it('lands on the dashboard', function (): void {
    Livewire::test(Inicio::class)->assertRedirect(Dashboard::getUrl());

    $this->actingAs($this->direccion->fresh())->get(TanquesAceite::getUrl(tenant: $this->tenant))->assertOk();
});
