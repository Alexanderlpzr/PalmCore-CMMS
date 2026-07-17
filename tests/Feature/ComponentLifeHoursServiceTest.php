<?php

use App\Domain\Assets\Enums\ComponentStatus;
use App\Domain\Assets\Services\ComponentLifeHoursService;
use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\Tenant;
use App\Models\User;

/**
 * El bug real: «Horas de vida» era un número que alguien escribía una vez y
 * quedaba congelado para siempre, mientras el horómetro del equipo seguía
 * acumulando horas de verdad sin que nadie las trasladara a la pieza.
 *
 * Estos tests prueban que ahora sí se mueve —y que se mueve solo para las
 * piezas que siguen en servicio, no para las que ya salieron.
 */
beforeEach(function (): void {
    $this->service = app(ComponentLifeHoursService::class);
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
});

// ── El punto de partida ──────────────────────────────────────────────────────

it('anchors a new component to the equipment meter as it stood today', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 4000,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'worked_hours' => null,
        'meter_reading_baseline' => null,
    ]);

    // «Esta pieza ya llevaba 200 h cuando la registré en Fronda.»
    $this->service->initializeBaseline($component, startingHours: 200);

    expect($component->refresh()->worked_hours)->toBe(200.0)
        ->and($component->meter_reading_baseline)->toBe(4000.0);
});

it('starts a component with no known history at zero instead of guessing', function (): void {
    // «No inventes datos»: sin un número que alguien haya dado, se arranca en
    // cero desde hoy — no se inventa cuánto llevaba trabajando antes.
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 1000,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create();

    $this->service->initializeBaseline($component);

    expect($component->refresh()->worked_hours)->toBe(0.0)
        ->and($component->meter_reading_baseline)->toBe(1000.0);
});

// ── El avance automático — el bug en sí ──────────────────────────────────────

it('advances worked_hours by exactly what the equipment meter advanced', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 5000,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'status' => ComponentStatus::Active->value,
        'worked_hours' => 200,
        'meter_reading_baseline' => 5000,
    ]);

    // El equipo trabajó 300 h más desde que se fijó el punto de partida.
    $equipment->update(['accumulated_meter_reading' => 5300]);

    $updated = $this->service->syncForEquipment($equipment->fresh());

    expect($updated)->toBe(1)
        ->and($component->refresh()->worked_hours)->toBe(500.0)
        ->and($component->meter_reading_baseline)->toBe(5300.0);
});

it('does not double count on a second sync with no new hours', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 1000,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'status' => ComponentStatus::Active->value,
        'worked_hours' => 0,
        'meter_reading_baseline' => 0,
    ]);

    $this->service->syncForEquipment($equipment);
    expect($component->refresh()->worked_hours)->toBe(1000.0);

    // Nada avanzó desde el último sync: la segunda pasada no debe sumar de nuevo.
    $second = $this->service->syncForEquipment($equipment->fresh());

    expect($second)->toBe(0)
        ->and($component->refresh()->worked_hours)->toBe(1000.0);
});

it('freezes the clock for a component that already left service', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 5000,
    ]);

    // Este rodamiento falló a las 4.200 h. Ese número es historia, no algo que
    // deba seguir subiendo porque el equipo sigue andando con otro rodamiento.
    $failed = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'status' => ComponentStatus::Failed->value,
        'worked_hours' => 4200,
        'meter_reading_baseline' => 4200,
    ]);

    $this->service->syncForEquipment($equipment);

    expect($failed->refresh()->worked_hours)->toBe(4200.0);
});

it('skips a component that was never anchored to a baseline', function (): void {
    // Componentes creados directo por factory (sin pasar por el servicio) no
    // tienen baseline: no se les inventa uno, simplemente no se tocan.
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 5000,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'worked_hours' => 300,
        'meter_reading_baseline' => null,
    ]);

    $updated = $this->service->syncForEquipment($equipment);

    expect($updated)->toBe(0)
        ->and($component->refresh()->worked_hours)->toBe(300.0);
});

it('never lets another tenant equipment advance this component', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create([
        'tenant_id' => $otherTenant->id,
        'accumulated_meter_reading' => 9000,
    ]);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 1000,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'status' => ComponentStatus::Active->value,
        'worked_hours' => 0,
        'meter_reading_baseline' => 0,
    ]);

    $this->service->syncForEquipment($otherEquipment);

    expect($component->refresh()->worked_hours)->toBe(0.0);
});

// ── Corrección manual / reemplazo ────────────────────────────────────────────

it('rebaselines to a new true value, same as resetting a meter', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'accumulated_meter_reading' => 8000,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'worked_hours' => 4500,
        'meter_reading_baseline' => 6000,
    ]);

    // Se reemplazó la pieza físicamente: el técnico corrige a 0.
    $this->service->rebaseline($component, 0);

    expect($component->refresh()->worked_hours)->toBe(0.0)
        ->and($component->meter_reading_baseline)->toBe(8000.0);
});

it('clears the baseline back to "unknown"', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 3000]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'worked_hours' => 500,
        'meter_reading_baseline' => 2000,
    ]);

    $this->service->clear($component);

    expect($component->refresh()->worked_hours)->toBeNull()
        ->and($component->meter_reading_baseline)->toBeNull();
});

// ── Integración: una lectura real dispara el avance ──────────────────────────

it('advances every active component when a new meter reading is recorded', function (): void {
    $meterService = app(EquipmentMeterReadingService::class);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_meter_reading' => 1000,
        'accumulated_meter_reading' => 1000,
    ]);

    $bearing = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'name' => 'Rodamiento principal',
        'status' => ComponentStatus::Active->value,
        'worked_hours' => 200,
        'meter_reading_baseline' => 1000,
    ]);
    $replaced = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'name' => 'Sello viejo',
        'status' => ComponentStatus::Replaced->value,
        'worked_hours' => 3000,
        'meter_reading_baseline' => 3000,
    ]);

    // Es exactamente el flujo real: alguien camina la planta y registra la ronda.
    $meterService->record($equipment, 1450.0, $this->user, MeterReadingUnit::Hours);

    expect($bearing->refresh()->worked_hours)->toBe(650.0) // 200 + 450
        ->and($replaced->refresh()->worked_hours)->toBe(3000.0); // congelado
});

it('keeps advancing across a meter reset, because the equipment accumulated total never goes backward', function (): void {
    $meterService = app(EquipmentMeterReadingService::class);
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_meter_reading' => 10452,
        'accumulated_meter_reading' => 10452,
    ]);
    $component = EquipmentComponent::factory()->forEquipment($equipment)->create([
        'status' => ComponentStatus::Active->value,
        'worked_hours' => 500,
        'meter_reading_baseline' => 10452,
    ]);

    // El dial se cambió físicamente: la lectura baja, pero el acumulado sigue.
    $meterService->record($equipment, 158.0, $this->user, MeterReadingUnit::Hours);

    expect($component->refresh()->worked_hours)->toBe(658.0); // 500 + 158
});
