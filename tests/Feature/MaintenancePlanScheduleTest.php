<?php

use App\Domain\Maintenance\Enums\MaintenanceTimeFrequency;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Services\MaintenancePlanService;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

function createCalendarPlan(
    MaintenancePlanService $service,
    string $tenantId,
    string $equipmentId,
    MaintenanceTimeFrequency $frequency,
    string $cadenceMode = 'fixed',
    ?User $user = null,
) {
    return $service->create([
        'tenant_id' => $tenantId,
        'equipment_id' => $equipmentId,
        'name' => "PM {$frequency->value}",
        'trigger_source' => MaintenanceTriggerSource::Calendar->value,
        'time_frequency' => $frequency->value,
        'cadence_mode' => $cadenceMode,
    ], $user ?? User::factory()->create());
}

// ── Fixed cadence (calendar) ──────────────────────────────────────────────────

it('fixed cadence advances next_due_at from theoretical date (no drift)', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = createCalendarPlan($service, $tenant->id, $equipment->id, MaintenanceTimeFrequency::Monthly, 'fixed', $user);

    // Plan due on the 1st of next month
    $theoreticalDue = now()->startOfMonth()->addMonth();
    $service->activate($plan, $theoreticalDue);

    // Simulate WO creation and completion on the theoretical due date
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $completedAt = $theoreticalDue->copy()->addDays(2); // completed 2 days late
    $service->recordExecution($plan, $wo, $completedAt);

    $schedule = $plan->schedule->refresh();

    // Next due should be theoretical + 1 month (not completion + 1 month)
    $expectedNextDue = $theoreticalDue->copy()->addMonth();
    expect($schedule->next_due_at->toDateString())->toBe($expectedNextDue->toDateString());
});

it('fixed cadence counts skipped cycles when execution is very late', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = createCalendarPlan($service, $tenant->id, $equipment->id, MaintenanceTimeFrequency::Monthly, 'fixed', $user);

    $theoreticalDue = now()->subMonths(3); // 3 months ago — so 2 cycles were skipped
    $service->activate($plan, $theoreticalDue);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $service->recordExecution($plan, $wo, now());

    $schedule = $plan->schedule->refresh();

    // At least 2 cycles were skipped (could be more depending on exact timing)
    expect($schedule->times_skipped)->toBeGreaterThanOrEqual(2)
        ->and($schedule->next_due_at->isFuture())->toBeTrue();
});

// ── Floating cadence (calendar) ───────────────────────────────────────────────

it('floating cadence calculates next_due_at from completion date', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = createCalendarPlan($service, $tenant->id, $equipment->id, MaintenanceTimeFrequency::Monthly, 'floating', $user);

    $firstDue = now()->subDays(5); // overdue
    $service->activate($plan, $firstDue);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $completedAt = now();
    $service->recordExecution($plan, $wo, $completedAt);

    $schedule = $plan->schedule->refresh();

    // Next due = completedAt + 1 month (floating)
    $expected = $completedAt->copy()->addMonth();
    expect($schedule->next_due_at->toDateString())->toBe($expected->toDateString());
});

// ── Meter cadence ─────────────────────────────────────────────────────────────

it('meter cadence calculates next_due_meter from completed reading', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id, 'current_meter_reading' => 500.0]);
    $user = User::factory()->create();

    $plan = $service->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'name' => 'PM 500H',
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
    ], $user);

    $service->activate($plan, null, 1000.0);

    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    $service->recordExecution($plan, $wo, now(), 1020.0);

    $schedule = $plan->schedule->refresh();

    // Next meter = 1020 + 500 = 1520
    expect($schedule->next_due_meter)->toBe(1520.0)
        ->and($schedule->last_completed_meter)->toBe(1020.0)
        ->and($schedule->times_executed)->toBe(1);
});

// ── Record execution increments counters ──────────────────────────────────────

it('record execution increments times_executed and updates last_completed', function () {
    $service = app(MaintenancePlanService::class);
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();

    $plan = createCalendarPlan($service, $tenant->id, $equipment->id, MaintenanceTimeFrequency::Monthly, 'floating', $user);
    $service->activate($plan, now()->subMonth());

    $wo1 = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);
    $wo2 = WorkOrder::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);

    $service->recordExecution($plan, $wo1, now()->subDays(5));
    $service->recordExecution($plan, $wo2, now());

    $schedule = $plan->schedule->refresh();

    expect($schedule->times_executed)->toBe(2)
        ->and($schedule->last_work_order_id)->toBe($wo2->id);
});
