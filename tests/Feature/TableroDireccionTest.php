<?php

use App\Domain\Analytics\Services\DirectionDashboard;
use App\Domain\Analytics\Services\MaintenanceCostReportService;
use App\Domain\HumanResources\Services\LaborCostReport;
use App\Filament\Pages\CostoDePersonal;
use App\Filament\Pages\CuadroCruce;
use App\Filament\Pages\Inicio;
use App\Filament\Pages\TableroDireccion;
use App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Customer;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\SalesDispatch;
use App\Models\SstFinding;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El tablero de Dirección: la planta, lo comercial, el costo de personal y el de
 * mantenimiento juntos, con el costo de transformación por tonelada. El personal y el
 * mantenimiento se simulan: sus cálculos tienen sus propias pruebas; aquí se prueba cómo
 * se juntan.
 *
 * Hoy es el 18 de noviembre de 2026; el tablero mira marzo.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 09:00'));

    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/Bogota']);
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    CurrentTenant::set($this->tenant);

    $persona = function (string $rol): User {
        $user = User::factory()->create(['is_active' => true]);
        $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
        $user->assignRole($rol);

        return $user;
    };

    $this->gerencia = $persona('gerencia-general');
    $this->directora = $persona('direccion-administrativa');
    $this->administrador = $persona('administrador-general');

    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    PlantMonthlyKpi::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $plant->id, 'year' => 2026, 'month' => 3, 'processed_tons' => 5000, 'kwh_grid' => 100000]);

    $cliente = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    SalesDispatch::factory()->invoiced(400_000_000)->create(['customer_id' => $cliente->id, 'loaded_on' => '2026-03-05', 'arrival_weight_tons' => 95, 'invoiced_on' => '2026-03-08']);
    SstFinding::factory()->create(['tenant_id' => $this->tenant->id]);

    // 150 M de personal y 50 M de mantenimiento cada mes, con 60 M presupuestados.
    $this->mock(LaborCostReport::class, fn ($mock) => $mock->shouldReceive('forRange')->andReturn([
        'rows' => [['name' => 'Nadie debe ver esto', 'total' => 1]],
        'areas' => ['procesos' => ['label' => 'Procesos', 'color' => '#1A7E42', 'people' => 40, 'jornal' => 100_000_000.0, 'novelties' => 0.0, 'surcharges' => 20_000_000.0, 'overtime' => 20_000_000.0, 'meals' => 10_000_000.0, 'total' => 150_000_000.0]],
        'totals' => ['people' => 40, 'jornal' => 100_000_000.0, 'novelties' => 0.0, 'surcharges' => 20_000_000.0, 'overtime' => 20_000_000.0, 'meals' => 10_000_000.0, 'total' => 150_000_000.0],
        'estimated' => false,
    ]));
    $this->mock(MaintenanceCostReportService::class, fn ($mock) => $mock->shouldReceive('monthlyReport')->andReturn([
        'total' => 50_000_000.0, 'labor' => 20_000_000.0, 'parts' => 25_000_000.0, 'consumables' => 5_000_000.0, 'external' => 0.0,
        'budget' => 60_000_000.0, 'work_order_count' => 12,
    ]));
});

it('adds personnel and maintenance per tonne processed, for the month and the year', function (): void {
    $d = app(DirectionDashboard::class)->build($this->tenant, 2026, 3);

    // (150 M + 50 M) / 5.000 tn.
    expect($d['current']['tons'])->toBe(5000.0)
        ->and($d['current']['cost'])->toBe(200_000_000.0)
        ->and($d['current']['cost_per_ton'])->toBe(40_000.0)
        ->and($d['current']['kwh_per_ton'])->toBe(20.0)
        ->and($d['current']['oil'])->toBe(95.0)
        ->and($d['current']['invoiced'])->toBe(400_000_000.0)
        // Enero a marzo: tres meses de costo sobre la misma fruta.
        ->and($d['ytd']['cost'])->toBe(600_000_000.0)
        ->and($d['ytd']['cost_per_ton'])->toBe(120_000.0)
        ->and($d['ytd']['maintenance_budget'])->toBe(180_000_000.0)
        // Diciembre no ha llegado: sin costo.
        ->and($d['months'][12]['personnel'])->toBeNull()
        ->and($d['personnel_areas'][0])->toMatchArray(['label' => 'Procesos', 'people' => 40, 'total' => 150_000_000.0])
        ->and($d['sst_open'])->toBe(1)
        ->and(json_encode($d))->not->toContain('Nadie debe ver esto');
});

it('is for management only, read-only, and lands the management account on it', function (): void {
    $this->actingAs($this->gerencia);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    expect(TableroDireccion::canAccess())->toBeTrue()
        // Solo el tablero: ni ventas, ni nómina, ni el cuadro cruce.
        ->and(CuadroCruce::canAccess())->toBeFalse()
        ->and(SalesDispatchResource::canViewAny())->toBeFalse()
        ->and(CostoDePersonal::canAccess())->toBeFalse();

    Livewire::test(Inicio::class)->assertRedirect(TableroDireccion::getUrl());

    Livewire::test(TableroDireccion::class, ['year' => 2026, 'month' => 3])
        ->assertOk()
        ->assertSee('Costo de transformación por tonelada')
        ->assertSee('$120.000 por tonelada')
        ->assertSee('Personal por área')
        // El detalle va en pestañas; las tarjetas de costos abren la suya.
        ->assertSeeHtml('data-pestana="composicion"')
        ->assertSeeHtml('$dispatch(\'fronda-ver\', \'composicion\')')
        ->assertDontSee('Nadie debe ver esto');

    foreach ([$this->directora, $this->administrador] as $otro) {
        $this->actingAs($otro);
        expect(TableroDireccion::canAccess())->toBeFalse();
    }
});
