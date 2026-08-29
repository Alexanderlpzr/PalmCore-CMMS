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
        // Un mes por fila, con los parámetros en columnas.
        ->assertSee('Enero')
        ->assertSee('RFF (t)')
        ->assertSee('KWh/RFF')
        ->assertSee('KWh TOTAL')
        ->assertSee('RED')
        ->assertSee('PLANTA')
        ->assertSee('TURBINA')
        ->assertSee('LIMPIA')
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

// ── El denominador que venía del calendario ──────────────────────────────────

it('toma la fruta del calendario cuando el cierre no la trae', function (): void {
    // El caso real que lo destapó: agosto de 2026 tenía la energía importada del Excel
    // —lo que crea la fila del cierre con processed_tons en su DEFAULT de cero— y la
    // producción cargada día a día en la pantalla semanal. El indicador decía «sin dato»
    // porque prefería la fila sin mirar si traía algo.
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8,
        'kwh_grid' => 1277, 'kwh_genset' => 12363, 'kwh_turbine' => 63454,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    foreach (['2026-08-03' => 196.35, '2026-08-04' => 223.75, '2026-08-05' => 246.00] as $dia => $t) {
        ProductionCalendarDay::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $this->plant->id,
            'calendar_date' => $dia,
            'programmed_hours' => 16,
            'processed_tons' => $t,
        ]);
    }

    $energia = $this->service->energySummary(
        $this->plant,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    // 666,10 t del calendario, y con ellas el indicador ya existe.
    expect($energia['processed_tons'])->toBe(666.1)
        ->and($energia['kwh_per_ton'])->toBe(115.74);
});

it('sigue prefiriendo la fruta del cierre cuando la trae', function (): void {
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 1,
        'processed_tons' => 5320,
        'kwh_grid' => 13828, 'kwh_genset' => 31115, 'kwh_turbine' => 118117,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    // Un día suelto en el calendario no debe desplazar al total del mes ya cerrado.
    ProductionCalendarDay::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'calendar_date' => '2026-01-15',
        'programmed_hours' => 20,
        'processed_tons' => 250,
    ]);

    $energia = $this->service->energySummary(
        $this->plant,
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
    );

    expect($energia['processed_tons'])->toBe(5320.0)
        ->and($energia['kwh_per_ton'])->toBe(30.65);
});

// ── Corregir desde la planilla ───────────────────────────────────────────────

it('ofrece corregir a quien tiene permiso', function (): void {
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8,
        'kwh_grid' => 1277, 'kwh_genset' => 12363, 'kwh_turbine' => 67160,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ])
        ->assertSee('Corregir un mes')
        // Enero vino del Excel y no tiene lecturas diarias detrás. Ofrecer «deshacer»
        // ahí era un boton que borraba el mes: limpiaba las marcas y recalculaba sobre
        // cero lecturas, dejando en nulo unas cifras que solo existen en esa fila.
        ->assertDontSee('Deshacer una correccion');
});

it('corrige el mes desde la propia tabla', function (): void {
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8,
        'processed_tons' => 3751.46,
        'kwh_grid' => 1277, 'kwh_genset' => 12363, 'kwh_turbine' => 67160,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ])->callAction('editMonth', data: [
        'month' => 8,
        'processed_tons' => 3751.46,
        'kwh_grid' => 1277,
        'kwh_genset' => 12363,
        'kwh_turbine' => 63454,
    ]);

    $mes = PlantMonthlyKpi::withoutGlobalScopes()->where('month', 8)->first();

    expect($mes->kwh_turbine)->toBe(63454.0)
        ->and($mes->clean_energy_percentage)->toBe(82.31);
});

it('no ofrece corregir a quien solo puede mirar', function (): void {
    $ajeno = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $ajeno->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->actingAs($ajeno);

    Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ])->assertDontSee('Corregir un mes');
});

it('solo ofrece deshacer donde hay lecturas a las que volver', function (): void {
    // Agosto con lecturas diarias y fijado a mano: aqui si hay vuelta atras.
    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);
    app(EnergyMeterReadingService::class)
        ->record($turbina, 2_463_979, $this->user, Carbon::parse('2026-08-19'));

    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8,
        'kwh_turbine' => 999,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ])->assertSee('Deshacer una correcci');
});

// ── La tabla y las tarjetas, del mismo sitio ─────────────────────────────────

it('toma la fruta del calendario cuando el cierre la trae en cero', function (): void {
    // El caso real que lo destapó: agosto de 2026 importado del Excel —lo que crea la
    // fila con processed_tons en su DEFAULT de cero— y la producción cargada día a día.
    // La tabla mostraba «0 t» y «—» junto a unas tarjetas que decían 3.751 t y 20,55.
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8,
        'kwh_grid' => 1277, 'kwh_genset' => 12363, 'kwh_turbine' => 63454,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    foreach (['2026-08-03' => 196.35, '2026-08-04' => 223.75, '2026-08-05' => 246.00] as $f => $t) {
        ProductionCalendarDay::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $this->plant->id,
            'calendar_date' => $f,
            'programmed_hours' => 16,
            'processed_tons' => $t,
        ]);
    }

    $filas = $this->service->monthlyEnergyRows($this->plant, 2026);

    // 666,10 t del calendario, y con ellas el indicador existe: 77.094 / 666,10.
    expect($filas[8]['processed_tons'])->toBe(666.1)
        ->and($filas[8]['kwh_total'])->toBe(77094.0)
        ->and($filas[8]['kwh_per_ton'])->toBe(115.74);
});

it('deja en null el mes sin fruta por ningún lado, no en cero', function (): void {
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8,
        'kwh_grid' => 1277, 'kwh_genset' => 12363, 'kwh_turbine' => 63454,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);

    $filas = $this->service->monthlyEnergyRows($this->plant, 2026);

    // Un cero afirmaría que no se procesó fruta; el guion dice que no se sabe.
    expect($filas[8]['processed_tons'])->toBeNull()
        ->and($filas[8]['kwh_per_ton'])->toBeNull()
        // El consumo sí se conoce, y se muestra.
        ->and($filas[8]['kwh_total'])->toBe(77094.0);
});

// ── El día, dentro del mes ───────────────────────────────────────────────────

it('despliega los días del mes que se pulsa', function (): void {
    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);
    $lecturas = app(EnergyMeterReadingService::class);
    $lecturas->record($turbina, 2_519_653, $this->user, Carbon::parse('2026-08-18'));
    $lecturas->record($turbina, 2_527_433, $this->user, Carbon::parse('2026-08-19'));

    Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ])
        ->assertDontSee('consumo')
        ->call('toggleMonth', 8)
        ->assertSet('openMonths', [8])
        ->assertSee('consumo')
        // 2.527.433 − 2.519.653
        ->assertSee('7.780');
});

it('deja varios meses abiertos a la vez, y cierra solo el que se pulsa', function (): void {
    // Antes se cerraba el anterior al abrir otro, y comparar dos meses obligaba a ir y
    // volver. Es la diferencia real con la agrupación de Equipos.
    $componente = Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ]);

    $componente->call('toggleMonth', 8)->assertSet('openMonths', [8])
        ->call('toggleMonth', 3)->assertSet('openMonths', [8, 3])
        ->call('toggleMonth', 8)->assertSet('openMonths', [3]);
});

it('pliega todos los meses de una vez', function (): void {
    $componente = Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ]);

    // El botón solo tiene sentido cuando hay algo que plegar.
    $componente->assertDontSee('Plegar todo')
        ->call('toggleMonth', 8)
        ->call('toggleMonth', 3)
        ->assertSee('Plegar todo')
        ->call('collapseAllMonths')
        ->assertSet('openMonths', [])
        ->assertDontSee('Plegar todo');
});

it('dice que el mes no tiene días en vez de abrir una tabla vacía', function (): void {
    EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);

    Livewire::test(PlantEnergyYearTableWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'year', 'year' => 2026],
    ])
        ->call('toggleMonth', 3)
        ->assertSee('no tiene ningún día registrado');
});
