<?php

use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\EnergyMeter;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\EquipmentMeterReading;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Los kWh no pueden filtrarse al dominio de mantenimiento.
 *
 * Este archivo existe para proteger una decisión de arquitectura, no una función.
 * Registrar el consumo eléctrico en `equipment_meter_readings` habría sido el atajo
 * obvio —la forma del dato es idéntica— pero tres consumidores de esa tabla suman el
 * `delta` **sin mirar la unidad**: las horas de vida de los componentes, el informe de
 * horas trabajadas y los totales de la cuadrícula de horómetros.
 *
 * Un kWh que entre ahí se convierte en una hora de vida de los álabes de la turbina, y
 * nadie lo nota hasta que un preventivo se dispara con años de adelanto.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create(['is_active' => true]);

    // La turbina, que en el inventario real existe como equipo Y como contador.
    $this->turbina = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->componente = EquipmentComponent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->turbina->id,
        'worked_hours' => 1_200,
        'meter_reading_baseline' => 0,
    ]);

    $this->contador = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => $this->turbina->id,
    ]);

    $this->service = app(EnergyMeterReadingService::class);
});

function anotarEnergia(object $test, float $valor, string $fecha): void
{
    $test->service->record(
        meter: $test->contador,
        readingValue: $valor,
        recordedBy: $test->user,
        readingDate: Carbon::parse($fecha),
    );
}

it('no escribe ni una fila en las lecturas de horómetro', function (): void {
    anotarEnergia($this, 2_463_979, '2026-07-31');
    anotarEnergia($this, 2_527_433, '2026-08-19');

    expect(EquipmentMeterReading::count())->toBe(0);
});

it('no toca las horas de vida de los componentes del equipo enlazado', function (): void {
    anotarEnergia($this, 2_463_979, '2026-07-31');
    anotarEnergia($this, 2_527_433, '2026-08-19');

    // 63.454 kWh de consumo. Si esto se hubiera modelado como horómetro, el componente
    // habría envejecido 63.454 horas de golpe — más de siete años en un día.
    expect($this->componente->fresh()->worked_hours)->toBe(1_200.0);
});

it('no aparece en el informe de horas trabajadas', function (): void {
    anotarEnergia($this, 2_463_979, '2026-07-31');
    anotarEnergia($this, 2_527_433, '2026-08-19');

    $resumen = app(EquipmentMeterReadingService::class)->workedHoursSummary(
        $this->tenant->id,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31')->endOfDay(),
    );

    expect($resumen)->toBeEmpty();
});

it('no altera el horómetro acumulado del equipo enlazado', function (): void {
    $antes = (float) $this->turbina->accumulated_meter_reading;

    anotarEnergia($this, 2_463_979, '2026-07-31');
    anotarEnergia($this, 2_527_433, '2026-08-19');

    expect((float) $this->turbina->fresh()->accumulated_meter_reading)->toBe($antes);
});

it('el contador de red pública no necesita equipo', function (): void {
    $red = EnergyMeter::factory()->grid()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'equipment_id' => null,
    ]);

    // La acometida de la electrificadora no es un activo mantenible. Que este caso sea
    // legítimo es la razón por la que los contadores no son equipos.
    expect($red->equipment_id)->toBeNull()
        ->and($red->equipment)->toBeNull();

    $this->service->record($red, 388_349, $this->user, Carbon::parse('2026-07-31'));
    $lectura = $this->service->record($red, 389_626, $this->user, Carbon::parse('2026-08-19'));

    expect($lectura->delta)->toBe(1277.0);
});
