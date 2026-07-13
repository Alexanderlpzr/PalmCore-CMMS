<?php

use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;

it('records a meter reading and updates equipment cache', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => null]);
    $user = User::factory()->create();

    $reading = $service->record($equipment, 1500.0, $user, MeterReadingUnit::Hours);

    expect($reading)->toBeInstanceOf(EquipmentMeterReading::class)
        ->and($reading->reading_value)->toBe(1500.0)
        ->and($reading->reading_unit)->toBe(MeterReadingUnit::Hours)
        ->and($equipment->fresh()->current_meter_reading)->toBe(1500.0);
});

it('records notes when provided', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $reading = $service->record($equipment, 500.0, $user, MeterReadingUnit::Hours, null, 'Lectura inicial al arrancar turno');

    expect($reading->notes)->toBe('Lectura inicial al arrancar turno');
});

/**
 * Reemplaza al antiguo «throws RuntimeException on backwards reading».
 *
 * Los horómetros SÍ retroceden: se cambian. El caso real del Pajuil es un dial
 * que marcaba 10.452 h y a la semana marcaba 158 h. Rechazar esa lectura no
 * protegía el dato, impedía registrarlo — y un plan preventivo por horómetro que
 * no puede recibir lecturas es un plan preventivo que no se ejecuta.
 */
it('accepts a backwards reading as a meter reset instead of rejecting it', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'current_meter_reading' => 2000.0,
        'accumulated_meter_reading' => 2000.0,
    ]);
    $user = User::factory()->create();

    $reading = $service->record($equipment, 1500.0, $user);

    expect($reading->is_reset)->toBeTrue()
        ->and($reading->previous_value)->toBe(2000.0)
        ->and($reading->delta)->toBe(1500.0)
        ->and($equipment->fresh()->accumulated_meter_reading)->toBe(3500.0);
});

it('allows equal reading (idempotent entry)', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => 1000.0]);
    $user = User::factory()->create();

    // Same value should NOT throw — it is not backwards
    $reading = $service->record($equipment, 1000.0, $user);

    expect($reading->reading_value)->toBe(1000.0);
});

it('currentReading returns cached value from equipment', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => 750.5]);

    expect($service->currentReading($equipment))->toBe(750.5);
});

it('currentReading returns null when no readings exist', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => null]);

    expect($service->currentReading($equipment))->toBeNull();
});

it('does not share meter readings between tenants', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id, 'current_meter_reading' => 500.0]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id, 'current_meter_reading' => null]);
    $user = User::factory()->create();

    $service->record($equipB, 100.0, $user);

    // Equipment A is unaffected
    expect($equipA->fresh()->current_meter_reading)->toBe(500.0);
});

// ── Horas acumuladas: el número contra el que se programa un preventivo ───────

it('accumulates consumption between readings', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $user = User::factory()->create();

    $service->record($equipment, 10_000.0, $user);
    $reading = $service->record($equipment->fresh(), 10_120.0, $user);

    // La primera lectura no inventa consumo: solo fija el punto de partida.
    expect($reading->delta)->toBe(120.0)
        ->and($reading->accumulated_value)->toBe(120.0)
        ->and($service->accumulatedReading($equipment->fresh()))->toBe(120.0);
});

it('keeps accumulating normally after a meter swap', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $user = User::factory()->create();

    $service->record($equipment, 500.0, $user);
    $service->record($equipment->fresh(), 600.0, $user);   // +100
    $service->record($equipment->fresh(), 50.0, $user);    // cambio de dial: +50
    $reading = $service->record($equipment->fresh(), 130.0, $user); // +80

    expect($reading->is_reset)->toBeFalse()
        ->and($reading->delta)->toBe(80.0)
        ->and($reading->accumulated_value)->toBe(230.0);
});

it('refuses a negative reading', function () {
    $service = app(EquipmentMeterReadingService::class);
    $equipment = Equipment::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(fn () => $service->record($equipment, -5.0, User::factory()->create()))
        ->toThrow(InvalidArgumentException::class);
});

// ── La ronda diaria: 30 horómetros de una sentada ─────────────────────────────

it('records the whole daily round and reports only the rows that failed', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $first = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => null]);
    $second = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => null]);
    $user = User::factory()->create();

    $result = $service->recordBulk([
        ['equipment_id' => $first->id, 'reading_value' => 1_200],
        ['equipment_id' => $second->id, 'reading_value' => 3_400],
        ['equipment_id' => $first->id, 'reading_value' => -1],
    ], $user, $tenant->id);

    // Una lectura mala no puede tumbar las otras 29.
    expect($result['recorded'])->toHaveCount(2)
        ->and($result['failed'])->toHaveCount(1)
        ->and($result['failed'][0]['equipment_id'])->toBe($first->id)
        ->and($first->fresh()->current_meter_reading)->toBe(1200.0);
});

// ── Consumo y «días faltantes» ────────────────────────────────────────────────

it('measures the pace of consumption per day', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $user = User::factory()->create();

    $service->record($equipment, 1_000.0, $user, recordedAt: now()->subDays(10));
    $service->record($equipment->fresh(), 1_100.0, $user, recordedAt: now()->subDays(5));
    $service->record($equipment->fresh(), 1_200.0, $user, recordedAt: now());

    // 200 h consumidas en los 10 días que separan la primera de la última lectura.
    expect($service->consumptionPerDay($equipment->fresh()))->toBe(20.0);
});

it('says nothing about the pace when there is not enough history', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => null]);

    $service->record($equipment, 1_000.0, User::factory()->create());

    expect($service->consumptionPerDay($equipment->fresh()))->toBeNull();
});

it('projects the days left until the next preventive falls due', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $user = User::factory()->create();

    $service->record($equipment, 1_000.0, $user, recordedAt: now()->subDays(10));
    $service->record($equipment->fresh(), 1_200.0, $user, recordedAt: now());

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_meter' => 500,
    ]);

    // Acumulado 200 h, faltan 300 h, ritmo 20 h/día → 15 días.
    expect($service->daysUntilDue($equipment->fresh(), $plan->fresh()))->toBe(15);
});

it('reports zero days left when the preventive is already due', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'current_meter_reading' => null,
        'accumulated_meter_reading' => 0,
    ]);
    $user = User::factory()->create();

    $service->record($equipment, 1_000.0, $user, recordedAt: now()->subDays(10));
    $service->record($equipment->fresh(), 1_600.0, $user, recordedAt: now());

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_meter' => 500,
    ]);

    expect($service->daysUntilDue($equipment->fresh(), $plan->fresh()))->toBe(0);
});

it('does not guess a projection for a plan with no meter due point', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_meter' => null,
    ]);

    expect($service->daysUntilDue($equipment, $plan->fresh()))->toBeNull();
});
