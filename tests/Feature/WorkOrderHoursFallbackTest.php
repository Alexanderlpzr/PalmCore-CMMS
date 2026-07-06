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

// Regression: Carbon 3 changed diffInMinutes() to return a *signed* difference
// by default (Carbon 2 always returned an absolute value). Data-entry mistakes
// where "fin planificado" ends up earlier than "inicio planificado" on the same
// day used to produce a negative duration, which format_hours_minutes() then
// silently dropped as "no value" instead of showing the (absolute) duration.

it('plannedHours returns a positive duration even if planned_end_at was entered before planned_start_at', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'planned_labor_hours' => null,
        'planned_start_at' => '2026-07-04 21:27:00',
        'planned_end_at' => '2026-07-04 12:27:00',
    ]);

    expect($wo->plannedHours())->toBe(9.0);
});

it('actualHours returns a positive duration even if actual_end_at was entered before actual_start_at', function () {
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'actual_labor_hours' => null,
        'actual_start_at' => '2026-07-06 10:00:00',
        'actual_end_at' => '2026-07-06 09:00:00',
    ]);

    expect($wo->actualHours())->toBe(1.0);
});
