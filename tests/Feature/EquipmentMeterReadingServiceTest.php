<?php

use App\Domain\Maintenance\Enums\MeterReadingUnit;
use App\Domain\Maintenance\Services\EquipmentMeterReadingService;
use App\Models\Equipment;
use App\Models\EquipmentMeterReading;
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

it('throws RuntimeException on backwards reading', function () {
    $service = app(EquipmentMeterReadingService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => 2000.0]);
    $user = User::factory()->create();

    expect(fn () => $service->record($equipment, 1500.0, $user))
        ->toThrow(RuntimeException::class, 'Los horómetros no retroceden');
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
