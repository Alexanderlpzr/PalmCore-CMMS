<?php

use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\PlantProcess;
use App\Models\WorkOrder;

it('persists and casts the OT log fields (proceso, área, ejecutante, horómetro)', function () {
    $workOrder = WorkOrder::factory()->create([
        'process' => PlantProcess::Extraccion->value,
        'maintenance_area' => MaintenanceArea::Mecanico->value,
        'executed_by' => 'El mecánico y su auxiliar',
        'meter_reading' => 12345.5,
    ]);

    $workOrder->refresh();

    expect($workOrder->process)->toBe(PlantProcess::Extraccion)
        ->and($workOrder->maintenance_area)->toBe(MaintenanceArea::Mecanico)
        ->and($workOrder->executed_by)->toBe('El mecánico y su auxiliar')
        ->and($workOrder->meter_reading)->toBe(12345.5);
});

it('allows the OT log fields to be null', function () {
    $workOrder = WorkOrder::factory()->create();

    expect($workOrder->process)->toBeNull()
        ->and($workOrder->maintenance_area)->toBeNull()
        ->and($workOrder->executed_by)->toBeNull()
        ->and($workOrder->meter_reading)->toBeNull();
});
