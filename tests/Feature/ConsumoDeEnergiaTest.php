<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Filament\Pages\ConsumoDeEnergia;
use App\Filament\Widgets\Executive\PlantEnergyYearTableWidget;
use App\Models\EnergyMeter;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->service = app(PlantKpiService::class);
});

it('reproduce las cifras de la hoja para un mes importado', function (): void {
    // Enero de 2026 tal como está en el Excel.
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 1,
        'processed_tons' => 5320,
        'kwh_grid' => 13828, 'kwh_genset' => 31115, 'kwh_turbine' => 118117,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    $energia = $this->service->energySummary(
        $this->plant,
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
    );

    expect($energia['kwh_total'])->toBe(163060.0)
        ->and($energia['kwh_per_ton'])->toBe(30.65)
        ->and($energia['clean_energy_percentage'])->toBe(72.44);
});

it('calcula el mes en curso desde las lecturas, sin fila de cierre', function (): void {
    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);

    $service = app(EnergyMeterReadingService::class);
    $service->record($turbina, 2_463_979, $this->user, Carbon::parse('2026-07-31'));
    $service->record($turbina, 2_527_433, $this->user, Carbon::parse('2026-08-19'));

    ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-08-19',
        'programmed_hours' => 22,
        'processed_tons' => 1000,
    ]);

    $energia = $this->service->energySummary(
        $this->plant,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    // El consumo que prueba el acumulado del contador, no el que decía la hoja.
    expect($energia['kwh_turbine'])->toBe(63454.0)
        ->and($energia['clean_energy_percentage'])->toBe(100.0)
        ->and($energia['kwh_per_ton'])->toBe(63.45);
});

it('mezcla meses importados y meses con lecturas en un mismo rango', function (): void {
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 7,
        'processed_tons' => 5468,
        'kwh_grid' => 4872, 'kwh_genset' => 33629, 'kwh_turbine' => 101201,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    $red = EnergyMeter::factory()->grid()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);
    $service = app(EnergyMeterReadingService::class);
    $service->record($red, 388_349, $this->user, Carbon::parse('2026-07-31'));
    $service->record($red, 389_626, $this->user, Carbon::parse('2026-08-19'));

    $energia = $this->service->energySummary(
        $this->plant,
        Carbon::parse('2026-07-01'),
        Carbon::parse('2026-08-31'),
    );

    // Julio de la hoja (4.872 de red) más agosto de las lecturas (1.277).
    expect($energia['kwh_grid'])->toBe(6149.0);
});

it('no inventa un porcentaje de energía limpia sin dato de turbina', function (): void {
    // Enero de 2025: la hoja trae un guion en turbina.
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2025, 'month' => 1,
        'processed_tons' => 4966.1,
        'kwh_grid' => 9240, 'kwh_genset' => 117981, 'kwh_turbine' => null,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    $energia = $this->service->energySummary(
        $this->plant,
        Carbon::parse('2025-01-01'),
        Carbon::parse('2025-01-31'),
    );

    expect($energia['kwh_total'])->toBe(127221.0)
        ->and($energia['clean_energy_percentage'])->toBeNull();
});

it('el cierre mensual no pisa un mes importado', function (): void {
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 1,
        'kwh_grid' => 13828, 'kwh_genset' => 31115, 'kwh_turbine' => 118117,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    // El mes se recalcula, por ejemplo porque entró un paro atrasado. No hay lecturas
    // diarias de enero: sin la guarda, esto pondría la energía en cero.
    $this->service->snapshotMonth($this->plant, 2026, 1);

    $mes = PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2026)->where('month', 1)->first();

    expect($mes->kwh_turbine)->toBe(118117.0)
        ->and($mes->energy_is_imported)->toBeTrue();
});

it('se la esconde a quien no tiene el permiso', function (): void {
    $ajeno = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $ajeno->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($ajeno);

    expect(ConsumoDeEnergia::canAccess())->toBeFalse();
});

// ── La tabla anual ───────────────────────────────────────────────────────────

it('arma la tabla del año con las cifras de la planilla', function (): void {
    // Los tres primeros meses de 2026, tal como están en la hoja.
    $meses = [
        1 => ['tons' => 5320, 'red' => 13828, 'planta' => 31115, 'turbina' => 118117],
        2 => ['tons' => 5010, 'red' => 8002, 'planta' => 46351, 'turbina' => 71970],
        3 => ['tons' => 6394, 'red' => 6930, 'planta' => 26864, 'turbina' => 156265],
    ];

    foreach ($meses as $mes => $d) {
        PlantMonthlyKpi::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $this->plant->id,
            'year' => 2026, 'month' => $mes,
            'processed_tons' => $d['tons'],
            'kwh_grid' => $d['red'], 'kwh_genset' => $d['planta'], 'kwh_turbine' => $d['turbina'],
            'energy_is_imported' => true,
            'calculated_at' => now(),
        ]);
    }

    Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ])
        ->assertSee('Energía · 2026')
        // Las etiquetas son las de la planilla que la planta ya lee.
        ->assertSee('RFF/MES (t)')
        ->assertSee('KWh/RFF')
        ->assertSee('KWh TOTAL')
        ->assertSee('KWh RED PÚBLICA')
        ->assertSee('KWh PLANTA ELÉCTRICA')
        ->assertSee('KWh TURBINA')
        ->assertSee('ENERGÍA LIMPIA (%)')
        // Enero: 163.060 kWh y 30,65 kWh por tonelada, igual que la hoja.
        ->assertSee('163.060')
        ->assertSee('30,65')
        // Marzo, para que no pase por casualidad con un solo mes.
        ->assertSee('190.059')
        // El KWh/RFF del año NO es el promedio de los meses: es el total de kWh partido
        // por el total de fruta, 479.442 / 16.724 = 28,67. El promedio de los tres
        // ratios daría 28,53, dándole el mismo peso a un mes flojo que a uno de plena
        // cosecha.
        ->assertSee('479.442')
        ->assertSee('28,67')
        // Los nueve meses sin cargar se pintan con un guion, no con un cero.
        ->assertSee('—');
});
