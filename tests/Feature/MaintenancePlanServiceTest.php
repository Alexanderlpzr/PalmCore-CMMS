<?php

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Models\Equipment;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;

// ── Plan numbering ────────────────────────────────────────────────────────────

it('generates calendar plan number with frequency label', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'PRE-001']);

    $number = $service->generatePlanNumber(
        $tenant->id,
        $equipment->code,
        MaintenanceTriggerSource::Calendar,
        MaintenanceTimeFrequency::Monthly->value,
        null,
    );

    expect($number)->toBe('PM-PRE-001-MENSUAL');
});

it('generates meter plan number with interval', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'DIG-001']);

    $number = $service->generatePlanNumber(
        $tenant->id,
        $equipment->code,
        MaintenanceTriggerSource::Meter,
        null,
        500,
    );

    expect($number)->toBe('PM-DIG-001-500H');
});

it('appends -A suffix on collision', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'PRE-001']);
    $user = User::factory()->create();

    $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'Plan mensual',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
    ], $user);

    $number = $service->generatePlanNumber(
        $tenant->id,
        $equipment->code,
        MaintenanceTriggerSource::Calendar,
        MaintenanceTimeFrequency::Monthly->value,
        null,
    );

    expect($number)->toBe('PM-PRE-001-MENSUAL-A');
});

it('appends -B suffix when -A also exists', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'PRE-001']);
    $user = User::factory()->create();

    $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'Plan mensual 1',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
    ], $user);

    $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'Plan mensual 2',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
    ], $user);

    $number = $service->generatePlanNumber(
        $tenant->id,
        $equipment->code,
        MaintenanceTriggerSource::Calendar,
        MaintenanceTimeFrequency::Monthly->value,
        null,
    );

    expect($number)->toBe('PM-PRE-001-MENSUAL-B');
});

// ── Create ────────────────────────────────────────────────────────────────────

it('creates plan with auto-generated number and 1:1 schedule', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'code' => 'PRE-001']);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM Mensual Prensa',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
    ], $user);

    expect($plan->plan_number)->toBe('PM-PRE-001-MENSUAL')
        ->and($plan->is_active)->toBeFalse()
        ->and($plan->schedule)->toBeInstanceOf(MaintenanceSchedule::class)
        ->and($plan->schedule->times_executed)->toBe(0)
        ->and($plan->schedule->next_due_at)->toBeNull();
});

// ── Activate ──────────────────────────────────────────────────────────────────

it('activate sets next due date and marks plan active', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM Test',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
    ], $user);

    $firstDue = now()->addMonth();
    $schedule = $service->activate($plan, $firstDue);

    expect($plan->fresh()->is_active)->toBeTrue()
        ->and($schedule->next_due_at->toDateString())->toBe($firstDue->toDateString());
});

it('activate sets next due meter for meter-based plan', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => 1000.0]);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM Horómetro',
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ], $user);

    $schedule = $service->activate($plan, null, 1500.0);

    expect($plan->fresh()->is_active)->toBeTrue()
        ->and($schedule->next_due_meter)->toBe(1500.0);
});

// ── Overdue ───────────────────────────────────────────────────────────────────

it('is not overdue when next_due_at is in the future', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM Test',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
    ], $user);

    $service->activate($plan, now()->addDays(10));

    expect($service->isOverdue($plan))->toBeFalse();
});

it('is overdue when next_due_at is past grace period', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM Test',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
        'grace_period_days' => 2,
    ], $user);

    $service->activate($plan, now()->subDays(5)); // 5 days past, grace = 2 → overdue

    expect($service->isOverdue($plan))->toBeTrue();
});

it('is not overdue when within grace period', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM Test',
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => MaintenanceTimeFrequency::Monthly->value,
        'grace_period_days' => 7,
    ], $user);

    $service->activate($plan, now()->subDays(3)); // 3 days past, grace = 7 → not overdue

    expect($service->isOverdue($plan))->toBeFalse();
});

it('is overdue by meter when reading exceeds due point plus grace', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM Horómetro',
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'grace_meter_hours' => 50,
    ], $user);

    $service->activate($plan, null, 1000.0);

    // Due at 1000h, grace = 50h → overdue when reading >= 1050
    expect($service->isOverdue($plan, 1100.0))->toBeTrue()
        ->and($service->isOverdue($plan, 1040.0))->toBeFalse();
});
