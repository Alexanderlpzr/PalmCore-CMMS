<?php

use App\Models\Tenant;
use App\Models\WorkOrder;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

it('plannedHours uses the stored value when one was typed on the form', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'planned_labor_hours' => 4.5,
        'planned_start_at' => now(),
        'planned_end_at' => now()->addDays(10),
    ]);

    expect($wo->plannedHours())->toBe(4.5);
});

it('plannedHours falls back to the planned_start_at/planned_end_at interval when no value was typed', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'planned_labor_hours' => null,
        'planned_start_at' => now(),
        'planned_end_at' => now()->addHours(2)->addMinutes(30),
    ]);

    expect($wo->plannedHours())->toBe(2.5);
});

it('plannedHours is null when neither a value nor a full planned interval exists', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'planned_labor_hours' => null,
        'planned_start_at' => null,
        'planned_end_at' => null,
    ]);

    expect($wo->plannedHours())->toBeNull();
});

it('actualHours uses the stored aggregate when time logs produced one', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'actual_labor_hours' => 1.25,
        'actual_start_at' => now(),
        'actual_end_at' => now()->addHours(5),
    ]);

    expect($wo->actualHours())->toBe(1.25);
});

it('actualHours falls back to the actual_start_at/actual_end_at interval when no time logs were recorded', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'actual_labor_hours' => null,
        'actual_start_at' => now(),
        'actual_end_at' => now()->addMinutes(20),
    ]);

    expect($wo->actualHours())->toBe(0.33);
});

it('actualHours is null when the OT never started', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'actual_labor_hours' => null,
        'actual_start_at' => null,
        'actual_end_at' => null,
    ]);

    expect($wo->actualHours())->toBeNull();
});
