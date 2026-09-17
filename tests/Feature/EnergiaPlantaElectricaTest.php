<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Filament\Pages\Energia;
use App\Models\EnergyMeter;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\Plant;
use App\Models\PlantEnergyDailyLog;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
 * Los tres renglones de la planta eléctrica: cambios de fuente, horas y combustible.
 *
 * Lo que más se prueba aquí es que **las horas no se teclean**: salen del horómetro de los
 * generadores, y un mes con una sola lectura queda vacío en vez de decir cero horas. Cero
 * afirmaría que el generador no trabajó; vacío dice que nadie lo leyó dos veces, que es lo
 * único cierto.
 */
beforeEach(function (): void {
    Carbon::setTestNow('2026-09-17 08:00');

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

afterEach(fn () => Carbon::setTestNow());

function generador(string $nombre, bool $cuenta = true): Equipment
{
    return Equipment::factory()->create([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'name' => $nombre,
        'counts_as_power_plant' => $cuenta,
    ]);
}

function horometro(Equipment $equipo, string $cuando, float $valor): EquipmentMeterReading
{
    return app(EquipmentMeterReadingService::class)->record(
        equipment: $equipo->refresh(),
        readingValue: $valor,
        recordedBy: test()->admin,
        recordedAt: Carbon::parse($cuando),
    );
}

/** @return array{genset_hours: ?float, genset_fuel_gallons: ?float, energy_switch_count: ?int} */
function resumenDeSeptiembre(): array
{
    return app(PlantKpiService::class)->powerPlantSummary(
        test()->plant,
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-09-30'),
    );
}

// ── Las horas salen del horómetro ────────────────────────────────────────────

it('las horas del mes son lo que avanzó el horómetro', function (): void {
    $planta = generador('Planta Eléctrica de 1250 kVA');

    horometro($planta, '2026-09-02 07:00', 5_600);
    horometro($planta, '2026-09-20 07:00', 5_640);

    expect(resumenDeSeptiembre()['genset_hours'])->toBe(40.0);
});

it('con una sola lectura el mes queda vacío, no en cero', function (): void {
    // Es el caso de producción: los dos generadores tienen una única lectura, del 14 de
    // agosto. Cero horas diría que estuvieron parados el mes entero.
    $planta = generador('Planta Eléctrica de 1250 kVA');

    horometro($planta, '2026-09-02 07:00', 5_600);

    expect(resumenDeSeptiembre()['genset_hours'])->toBeNull();
});

it('suma las horas de los dos generadores', function (): void {
    $grande = generador('Planta Eléctrica de 1250 kVA');
    $chico = generador('Planta Eléctrica de 72 kVA');

    horometro($grande, '2026-09-02 07:00', 5_600);
    horometro($grande, '2026-09-20 07:00', 5_630);
    horometro($chico, '2026-09-02 07:00', 800);
    horometro($chico, '2026-09-20 07:00', 812);

    expect(resumenDeSeptiembre()['genset_hours'])->toBe(42.0);
});

it('no cuenta el horómetro de un equipo que no es planta eléctrica', function (): void {
    // Sin la marca, cualquier equipo medido en horas entraría en el informe de energía:
    // una prensa sumaría sus horas a las del generador.
    $prensa = generador('Prensa 3', cuenta: false);

    horometro($prensa, '2026-09-02 07:00', 1_000);
    horometro($prensa, '2026-09-20 07:00', 1_300);

    expect(resumenDeSeptiembre()['genset_hours'])->toBeNull();
});

it('no se lleva al mes las horas de otro mes', function (): void {
    $planta = generador('Planta Eléctrica de 1250 kVA');

    horometro($planta, '2026-08-02 07:00', 5_000);
    horometro($planta, '2026-08-30 07:00', 5_100);
    horometro($planta, '2026-09-10 07:00', 5_120);

    // Solo el avance leído dentro de septiembre: las 100 horas de agosto son de agosto.
    expect(resumenDeSeptiembre()['genset_hours'])->toBe(20.0);
});

// ── Combustible y cambios de fuente ──────────────────────────────────────────

it('suma el combustible y los cambios anotados en el mes', function (): void {
    PlantEnergyDailyLog::factory()->forPlant($this->plant)->create([
        'log_date' => '2026-09-03', 'fuel_gallons' => 120.5, 'energy_switch_count' => 2,
    ]);
    PlantEnergyDailyLog::factory()->forPlant($this->plant)->create([
        'log_date' => '2026-09-04', 'fuel_gallons' => 80.0, 'energy_switch_count' => 3,
    ]);

    $resumen = resumenDeSeptiembre();

    expect($resumen['genset_fuel_gallons'])->toBe(200.5)
        ->and($resumen['energy_switch_count'])->toBe(5);
});

it('un mes sin nada anotado queda vacío y no en cero', function (): void {
    $resumen = resumenDeSeptiembre();

    expect($resumen['genset_fuel_gallons'])->toBeNull()
        ->and($resumen['energy_switch_count'])->toBeNull();
});

it('cero galones sí es un dato', function (): void {
    // El día que no se cargó diésel cuenta como cero, y se distingue del día que nadie
    // anotó: es la misma regla que sostiene toda la planilla de energía.
    PlantEnergyDailyLog::factory()->forPlant($this->plant)->create([
        'log_date' => '2026-09-03', 'fuel_gallons' => 0, 'energy_switch_count' => null,
    ]);

    $resumen = resumenDeSeptiembre();

    expect($resumen['genset_fuel_gallons'])->toBe(0.0)
        ->and($resumen['energy_switch_count'])->toBeNull();
});

// ── La ronda diaria ──────────────────────────────────────────────────────────

it('la ronda guarda el horómetro en la misma tabla que el módulo de Horómetros', function (): void {
    $planta = generador('Planta Eléctrica de 1250 kVA');
    EnergyMeter::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id]);

    Livewire::test(Energia::class)
        ->set('data.plant_id', $this->plant->id)
        ->set('data.reading_date', '2026-09-17')
        ->set("data.power_plant.hours.{$planta->id}", 5_700)
        ->set('data.power_plant.fuel_gallons', 45.5)
        ->set('data.power_plant.switch_count', 2)
        ->call('save')
        ->assertHasNoErrors();

    // No es una copia: es la lectura del equipo, con su cadena de deltas y sus avisos de
    // mantenimiento por horas.
    expect(EquipmentMeterReading::where('equipment_id', $planta->id)->value('reading_value'))->toBe(5700.0)
        ->and(PlantEnergyDailyLog::where('plant_id', $this->plant->id)->first())
        ->fuel_gallons->toBe(45.5)
        ->energy_switch_count->toBe(2);
});

it('guardar dos veces el mismo día corrige la lectura en vez de duplicarla', function (): void {
    // Dos lecturas del mismo día darían un avance inventado: el mes sumaría un tramo que
    // el dial nunca recorrió.
    $planta = generador('Planta Eléctrica de 1250 kVA');

    $pantalla = Livewire::test(Energia::class)
        ->set('data.plant_id', $this->plant->id)
        ->set('data.reading_date', '2026-09-17')
        ->set("data.power_plant.hours.{$planta->id}", 5_700)
        ->call('save');

    $pantalla->set("data.power_plant.hours.{$planta->id}", 5_705)->call('save');

    $lecturas = EquipmentMeterReading::where('equipment_id', $planta->id)->get();

    expect($lecturas)->toHaveCount(1)
        ->and((float) $lecturas->first()->reading_value)->toBe(5705.0);
});

// ── El cierre del mes ────────────────────────────────────────────────────────

it('el cierre mensual guarda las tres cifras', function (): void {
    $planta = generador('Planta Eléctrica de 1250 kVA');
    horometro($planta, '2026-09-02 07:00', 5_600);
    horometro($planta, '2026-09-20 07:00', 5_640);
    PlantEnergyDailyLog::factory()->forPlant($this->plant)->create([
        'log_date' => '2026-09-05', 'fuel_gallons' => 300.0, 'energy_switch_count' => 4,
    ]);

    $kpi = app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 9);

    expect($kpi->genset_hours)->toBe(40.0)
        ->and($kpi->genset_fuel_gallons)->toBe(300.0)
        ->and($kpi->energy_switch_count)->toBe(4);
});

it('un mes cargado a mano conserva sus cifras cuando se recalcula', function (): void {
    // Los ocho meses de 2026 vienen de la hoja y no tienen lecturas detrás. Recalcular sin
    // esta guarda los vaciaría en la madrugada del día 1.
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026,
        'month' => 9,
        'energy_is_imported' => true,
        'genset_hours' => 73.0,
        'genset_fuel_gallons' => 1_239.9,
        'energy_switch_count' => 36,
        'calculated_at' => now(),
    ]);

    $kpi = app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 9);

    expect($kpi->genset_hours)->toBe(73.0)
        ->and($kpi->genset_fuel_gallons)->toBe(1239.9)
        ->and($kpi->energy_switch_count)->toBe(36);
});
