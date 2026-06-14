<?php

use App\Domain\Automation\Enums\AutomationEventType;
use App\Domain\Automation\Enums\AutomationMode;
use App\Domain\Automation\Services\AutomationService;
use App\Domain\Notifications\MaintenancePlanOverdueNotification;
use App\Domain\Notifications\ScheduleUpcomingNotification;
use App\Domain\Notifications\WorkOrderOverdueNotification;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Notification;

// ── Helpers ───────────────────────────────────────────────────────────────────

function automationTenant(): Tenant
{
    return Tenant::factory()->create();
}

function automationRule(Tenant $tenant, AutomationEventType $type, AutomationMode $mode): AutomationRule
{
    return AutomationRule::forceCreate([
        'tenant_id' => $tenant->id,
        'name' => $type->label(),
        'event_type' => $type->value,
        'mode' => $mode->value,
        'is_active' => true,
    ]);
}

function overdueScheduleFor(Tenant $tenant, ?User $responsible = null): MaintenancePlan
{
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'responsible_user_id' => $responsible?->id,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->overdue()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
    ]);

    return $plan;
}

// ── Disabled rule ─────────────────────────────────────────────────────────────

it('skips a disabled rule and records no execution', function () {
    Notification::fake();

    $tenant = automationTenant();
    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::Disabled);

    overdueScheduleFor($tenant);

    app(AutomationService::class)->executeRule($rule);

    expect(AutomationRuleExecution::count())->toBe(0);
    Notification::assertNothingSent();
});

// ── maintenance_plan_overdue — NotifyOnly ─────────────────────────────────────

it('sends notification for overdue plan in NotifyOnly mode', function () {
    Notification::fake();

    $tenant = automationTenant();
    $responsible = User::factory()->create(['is_active' => true]);
    $responsible->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::NotifyOnly);

    overdueScheduleFor($tenant, $responsible);

    app(AutomationService::class)->executeRule($rule);

    Notification::assertSentTo($responsible, MaintenancePlanOverdueNotification::class);

    expect(AutomationRuleExecution::where('action_taken', 'notified_overdue')->count())->toBe(1);
});

it('does not re-notify overdue plan when already notified in this cycle', function () {
    Notification::fake();

    $tenant = automationTenant();
    $responsible = User::factory()->create(['is_active' => true]);
    $responsible->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::NotifyOnly);
    $plan = overdueScheduleFor($tenant, $responsible);

    $service = app(AutomationService::class);
    $service->executeRule($rule);
    $service->executeRule($rule);  // second run

    Notification::assertSentToTimes($responsible, MaintenancePlanOverdueNotification::class, 1);
    expect(AutomationRuleExecution::count())->toBe(1);
});

// ── maintenance_plan_overdue — Automatic ─────────────────────────────────────

it('creates a MaintenanceRequest automatically for overdue plan', function () {
    Notification::fake();

    $tenant = automationTenant();
    $responsible = User::factory()->create(['is_active' => true]);
    $responsible->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::Automatic);

    overdueScheduleFor($tenant, $responsible);

    app(AutomationService::class)->executeRule($rule);

    $this->assertDatabaseHas('maintenance_requests', [
        'tenant_id' => $tenant->id,
        'request_type' => 'preventive',
    ]);

    expect(AutomationRuleExecution::where('action_taken', 'created_mr')->count())->toBe(1);
});

it('does not create a duplicate MR when an open MR already exists', function () {
    Notification::fake();

    $tenant = automationTenant();
    $responsible = User::factory()->create(['is_active' => true]);
    $responsible->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::Automatic);
    $plan = overdueScheduleFor($tenant, $responsible);

    $service = app(AutomationService::class);
    $service->executeRule($rule);
    $service->executeRule($rule);  // second run

    expect(MaintenanceRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

// ── Cycle reset ───────────────────────────────────────────────────────────────

it('re-notifies overdue plan after it was resolved (new cycle)', function () {
    Notification::fake();

    $tenant = automationTenant();
    $responsible = User::factory()->create(['is_active' => true]);
    $responsible->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::NotifyOnly);
    $plan = overdueScheduleFor($tenant, $responsible);

    $service = app(AutomationService::class);
    $service->executeRule($rule);  // first cycle — notified

    // Simulate plan execution: set last_completed_at to a time AFTER the execution
    $schedule = $plan->schedule;
    $schedule->update(['last_completed_at' => now()->addMinutes(5)]);

    // Plan becomes overdue again in a new cycle
    $service->executeRule($rule);

    Notification::assertSentToTimes($responsible, MaintenancePlanOverdueNotification::class, 2);
});

// ── R4 — Mode changed after dispatch ─────────────────────────────────────────

it('skips rule when mode was changed to Disabled after dispatch', function () {
    Notification::fake();

    $tenant = automationTenant();
    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::NotifyOnly);

    overdueScheduleFor($tenant);

    // Simulate mode change before execution
    AutomationRule::withoutGlobalScopes()->where('id', $rule->id)->update(['mode' => 'disabled']);

    app(AutomationService::class)->executeRule($rule);  // passes stale $rule

    expect(AutomationRuleExecution::count())->toBe(0);
    Notification::assertNothingSent();
});

// ── R3 — Deleted plan is skipped ─────────────────────────────────────────────

it('skips soft-deleted plan', function () {
    Notification::fake();

    $tenant = automationTenant();
    $responsible = User::factory()->create(['is_active' => true]);
    $responsible->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = automationRule($tenant, AutomationEventType::MaintenancePlanOverdue, AutomationMode::NotifyOnly);
    $plan = overdueScheduleFor($tenant, $responsible);

    $plan->delete();

    app(AutomationService::class)->executeRule($rule);

    Notification::assertNothingSent();
    expect(AutomationRuleExecution::count())->toBe(0);
});

// ── work_order_overdue ────────────────────────────────────────────────────────

it('escalates overdue work order to critical priority in Automatic mode', function () {
    Notification::fake();

    $tenant = automationTenant();
    $supervisor = User::factory()->create(['is_active' => true]);
    $supervisor->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = automationRule($tenant, AutomationEventType::WorkOrderOverdue, AutomationMode::Automatic);

    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => 'planned',
        'priority' => 'p3_medium',
        'planned_end_at' => now()->subDay(),
        'assigned_supervisor' => $supervisor->id,
    ]);

    app(AutomationService::class)->executeRule($rule);

    expect($wo->fresh()->priority->value)->toBe('p1_critical');
    Notification::assertSentTo($supervisor, WorkOrderOverdueNotification::class);
    expect(AutomationRuleExecution::where('action_taken', 'escalated')->count())->toBe(1);
});

// ── schedule_upcoming ─────────────────────────────────────────────────────────

it('sends upcoming notification when plan is due within configured days', function () {
    Notification::fake();

    $tenant = automationTenant();
    $responsible = User::factory()->create(['is_active' => true]);
    $responsible->tenants()->attach($tenant->id, ['joined_at' => now()]);

    $rule = AutomationRule::forceCreate([
        'tenant_id' => $tenant->id,
        'name' => 'Próximos vencimientos',
        'event_type' => AutomationEventType::ScheduleUpcoming->value,
        'mode' => AutomationMode::NotifyOnly->value,
        'is_active' => true,
        'configuration' => ['days_ahead' => 7],
    ]);

    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'responsible_user_id' => $responsible->id,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => now()->addDays(3),  // within 7-day window
    ]);

    app(AutomationService::class)->executeRule($rule);

    Notification::assertSentTo($responsible, ScheduleUpcomingNotification::class);
});
