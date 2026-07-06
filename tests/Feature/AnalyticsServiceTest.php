<?php

use App\Domain\Analytics\DTOs\TrendPoint;
use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\EquipmentKpi;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;

// ── Helpers ───────────────────────────────────────────────────────────────────

function analyticsTenant(): Tenant
{
    return Tenant::factory()->create();
}

function service(): AnalyticsService
{
    Cache::flush(); // prevent cache interference between tests

    return app(AnalyticsService::class);
}

// ── failuresByMonth ───────────────────────────────────────────────────────────

it('failuresByMonth returns exactly 12 TrendPoints', function () {
    $tenant = analyticsTenant();

    $points = service()->failuresByMonth($tenant->id);

    expect($points)->toHaveCount(12)
        ->and($points[0])->toBeInstanceOf(TrendPoint::class);
});

it('failuresByMonth only counts unplanned events (was_planned = false)', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    // now()->startOfMonth()->addDay(), not subDays(5): the trend bucket asserted
    // below is the CURRENT month, and subDays(5) rolls into the previous month
    // whenever the test runs in the first few days of a month.
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => true,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->failuresByMonth($tenant->id);
    $currentMonth = collect($points)->last();

    expect($currentMonth->count)->toBe(1);
});

it('failuresByMonth enforces tenant isolation', function () {
    $tenantA = analyticsTenant();
    $tenantB = analyticsTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenantA->id,
        'equipment_id' => $equipA->id,
        'was_planned' => false,
        'started_at' => now()->subDays(5),
    ]);

    $pointsB = service()->failuresByMonth($tenantB->id);
    $total = collect($pointsB)->sum('count');

    expect($total)->toBe(0);
});

it('failuresByMonth fills months with no events as value=0', function () {
    $tenant = analyticsTenant();

    $points = service()->failuresByMonth($tenant->id);

    // All points in an empty tenant should have value=0
    foreach ($points as $point) {
        expect($point->value)->toBe(0.0);
    }
});

it('failuresByMonth excludes ongoing events (ended_at = null)', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->ongoing()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'started_at' => now()->subDays(2),
    ]);

    $points = service()->failuresByMonth($tenant->id);
    $total = collect($points)->sum('count');

    expect($total)->toBe(0);
});

// ── failuresByMonth with an explicit period ────────────────────────────────────

it('failuresByMonth with an explicit from/to returns exactly that many months', function () {
    $tenant = analyticsTenant();

    $points = service()->failuresByMonth(
        $tenant->id,
        now()->subMonths(2)->startOfMonth(),
        now()->startOfMonth(),
    );

    expect($points)->toHaveCount(3);
});

it('failuresByMonth with a single-month range (from = to) returns exactly 1 point', function () {
    $tenant = analyticsTenant();

    $points = service()->failuresByMonth($tenant->id, now()->startOfMonth(), now()->startOfMonth());

    expect($points)->toHaveCount(1);
});

it('failuresByMonth with an explicit range only counts events inside that window', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    // Inside the requested range (2 months ago)
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'started_at' => now()->subMonths(2)->startOfMonth()->addDay(),
    ]);

    // Outside the requested range (this month)
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->failuresByMonth(
        $tenant->id,
        now()->subMonths(3)->startOfMonth(),
        now()->subMonths(2)->startOfMonth(),
    );

    expect(collect($points)->sum('count'))->toBe(1);
});

// ── downtimeTrend ─────────────────────────────────────────────────────────────

it('downtimeTrend converts duration_minutes to hours', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'duration_minutes' => 120,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->downtimeTrend($tenant->id);
    $currentMonth = collect($points)->last();

    expect($currentMonth->value)->toBe(2.0);
});

it('downtimeTrend includes planned events in total downtime', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'duration_minutes' => 60,
        'was_planned' => true,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->downtimeTrend($tenant->id);
    $currentMonth = collect($points)->last();

    expect($currentMonth->value)->toBe(1.0);
});

// ── mtbfTrend ─────────────────────────────────────────────────────────────────

it('mtbfTrend returns null value for months with no failures', function () {
    $tenant = analyticsTenant();

    $points = service()->mtbfTrend($tenant->id);

    foreach ($points as $point) {
        expect($point->value)->toBeNull();
    }
});

it('mtbfTrend calculates a positive value when there are failures', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'duration_minutes' => 120,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->mtbfTrend($tenant->id);
    $currentMonth = collect($points)->last();

    expect($currentMonth->value)->toBeFloat()->toBeGreaterThan(0);
});

it('mtbfTrend scoped to one equipment ignores other equipment failures', function () {
    $tenant = analyticsTenant();
    $equipmentA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipmentB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipmentB->id,
        'was_planned' => false,
        'duration_minutes' => 120,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->mtbfTrend($tenant->id, equipmentId: $equipmentA->id);
    $currentMonth = collect($points)->last();

    // Equipment A had no failures of its own, so its MTBF stays a gap (null),
    // even though the tenant as a whole had one (on equipment B).
    expect($currentMonth->value)->toBeNull();
});

// ── mttrTrend ─────────────────────────────────────────────────────────────────

it('mttrTrend returns null value for months with no failures', function () {
    $tenant = analyticsTenant();

    $points = service()->mttrTrend($tenant->id);

    foreach ($points as $point) {
        expect($point->value)->toBeNull();
    }
});

it('mttrTrend calculates hours correctly from duration_minutes', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    // 2 failures, each 60 min → MTTR = 60/60 = 1.0 h
    EquipmentDowntimeEvent::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'duration_minutes' => 60,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->mttrTrend($tenant->id);
    $currentMonth = collect($points)->last();

    expect($currentMonth->value)->toBe(1.0)
        ->and($currentMonth->count)->toBe(2);
});

it('mttrTrend scoped to one equipment only counts that equipment\'s failures', function () {
    $tenant = analyticsTenant();
    $equipmentA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipmentB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipmentA->id,
        'was_planned' => false,
        'duration_minutes' => 90,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipmentB->id,
        'was_planned' => false,
        'duration_minutes' => 300,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $points = service()->mttrTrend($tenant->id, equipmentId: $equipmentA->id);
    $currentMonth = collect($points)->last();

    // 90 min / 1 failure = 1.5 h — equipment B's 300 min must not leak in.
    expect($currentMonth->value)->toBe(1.5)
        ->and($currentMonth->count)->toBe(1);
});

// ── costByEquipment ───────────────────────────────────────────────────────────

it('costByEquipment returns equipment sorted by total cost descending', function () {
    $tenant = analyticsTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipA->id,
        'actual_cost_total' => 10000,
    ]);

    WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipB->id,
        'actual_cost_total' => 5000,
    ]);

    $points = service()->costByEquipment($tenant->id);

    expect($points[0]->value)->toBe(10000.0)
        ->and($points[1]->value)->toBe(5000.0);
});

it('costByEquipment enforces tenant isolation', function () {
    $tenantA = analyticsTenant();
    $tenantB = analyticsTenant();
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $tenantB->id,
        'equipment_id' => $equipB->id,
        'actual_cost_total' => 99999,
    ]);

    $points = service()->costByEquipment($tenantA->id);

    expect($points)->toBeEmpty();
});

it('costByEquipment excludes soft-deleted work orders', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $deleted = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'actual_cost_total' => 50000,
    ]);
    $deleted->delete();

    $points = service()->costByEquipment($tenant->id);

    expect($points)->toBeEmpty();
});

it('costByEquipment ignores work orders with null actual_cost_total', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'actual_cost_total' => null,
    ]);

    $points = service()->costByEquipment($tenant->id);

    expect($points)->toBeEmpty();
});

// ── paretoFailures ────────────────────────────────────────────────────────────

it('paretoFailures only includes unplanned events', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->planned()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'started_at' => now()->subDays(5),
    ]);

    $points = service()->paretoFailures($tenant->id);

    expect($points)->toBeEmpty();
});

it('paretoFailures enforces tenant isolation', function () {
    $tenantA = analyticsTenant();
    $tenantB = analyticsTenant();
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    EquipmentDowntimeEvent::factory()->count(3)->create([
        'tenant_id' => $tenantB->id,
        'equipment_id' => $equipB->id,
        'was_planned' => false,
        'started_at' => now()->subDays(5),
    ]);

    $points = service()->paretoFailures($tenantA->id);

    expect($points)->toBeEmpty();
});

it('paretoFailures sorts by failure count descending', function () {
    $tenant = analyticsTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipA->id,
        'was_planned' => false,
        'started_at' => now()->subDays(5),
    ]);

    EquipmentDowntimeEvent::factory()->count(1)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipB->id,
        'was_planned' => false,
        'started_at' => now()->subDays(5),
    ]);

    $points = service()->paretoFailures($tenant->id);

    expect($points[0]->count)->toBe(3)
        ->and($points[1]->count)->toBe(1);
});

// ── paretoFailuresByMode ──────────────────────────────────────────────────────

it('paretoFailuresByMode groups unplanned failures by mode and labels them', function () {
    $tenant = analyticsTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    // 3 bearing failures across two machines + 1 electrical
    EquipmentDowntimeEvent::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipA->id,
        'was_planned' => false,
        'failure_mode' => FailureMode::Bearing->value,
        'started_at' => now()->subDays(5),
    ]);
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipB->id,
        'was_planned' => false,
        'failure_mode' => FailureMode::Bearing->value,
        'started_at' => now()->subDays(5),
    ]);
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipB->id,
        'was_planned' => false,
        'failure_mode' => FailureMode::Electrical->value,
        'started_at' => now()->subDays(5),
    ]);

    $points = service()->paretoFailuresByMode($tenant->id);

    // Dominant mode first, with human-readable label — across machines.
    expect($points[0]->label)->toBe(FailureMode::Bearing->label())
        ->and($points[0]->count)->toBe(3)
        ->and($points[1]->label)->toBe(FailureMode::Electrical->label())
        ->and($points[1]->count)->toBe(1);
});

it('paretoFailuresByMode ignores events without a classified mode', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'was_planned' => false,
        'failure_mode' => null,
        'started_at' => now()->subDays(5),
    ]);

    expect(service()->paretoFailuresByMode($tenant->id))->toBeEmpty();
});

// ── preventiveCompliance ──────────────────────────────────────────────────────

it('preventiveCompliance counts on-schedule vs overdue active plans', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    // On schedule
    $planA = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'is_active' => true]);
    MaintenanceSchedule::factory()->create(['tenant_id' => $tenant->id, 'maintenance_plan_id' => $planA->id, 'next_due_at' => now()->addMonth()]);

    // Overdue
    $planB = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'is_active' => true]);
    MaintenanceSchedule::factory()->create(['tenant_id' => $tenant->id, 'maintenance_plan_id' => $planB->id, 'next_due_at' => now()->subDays(5)]);

    $result = service()->preventiveCompliance($tenant->id);

    expect($result['total'])->toBe(2)
        ->and($result['on_schedule'])->toBe(1)
        ->and($result['overdue'])->toBe(1)
        ->and($result['compliance'])->toBe(50.0);
});

it('preventiveCompliance excludes inactive plans and enforces tenant isolation', function () {
    $tenant = analyticsTenant();
    $other = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $active = MaintenancePlan::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'is_active' => true]);
    MaintenanceSchedule::factory()->create(['tenant_id' => $tenant->id, 'maintenance_plan_id' => $active->id, 'next_due_at' => now()->addMonth()]);

    $inactive = MaintenancePlan::factory()->inactive()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id]);
    MaintenanceSchedule::factory()->create(['tenant_id' => $tenant->id, 'maintenance_plan_id' => $inactive->id, 'next_due_at' => now()->subDays(5)]);

    // Other tenant's plan must not leak in
    $otherEquip = Equipment::factory()->create(['tenant_id' => $other->id]);
    $otherPlan = MaintenancePlan::factory()->create(['tenant_id' => $other->id, 'equipment_id' => $otherEquip->id, 'is_active' => true]);
    MaintenanceSchedule::factory()->create(['tenant_id' => $other->id, 'maintenance_plan_id' => $otherPlan->id, 'next_due_at' => now()->subDays(5)]);

    $result = service()->preventiveCompliance($tenant->id);

    expect($result['total'])->toBe(1)
        ->and($result['on_schedule'])->toBe(1)
        ->and($result['compliance'])->toBe(100.0);
});

it('preventiveCompliance returns null compliance when no active scheduled plans exist', function () {
    $tenant = analyticsTenant();

    expect(service()->preventiveCompliance($tenant->id)['compliance'])->toBeNull();
});

// ── plannedVsCorrective ───────────────────────────────────────────────────────

it('plannedVsCorrective counts completed WOs by nature over the last 12 months', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    $base = ['tenant_id' => $tenant->id, 'equipment_id' => $equipment->id, 'completed_at' => now()->subDays(10)];

    WorkOrder::factory()->count(3)->create([...$base, 'work_order_type' => 'preventive', 'status' => 'closed']);
    WorkOrder::factory()->create([...$base, 'work_order_type' => 'predictive', 'status' => 'verified']);
    WorkOrder::factory()->create([...$base, 'work_order_type' => 'corrective', 'status' => 'completed']);

    // Excluded: cancelled, out-of-window, and improvement type
    WorkOrder::factory()->create([...$base, 'work_order_type' => 'corrective', 'status' => 'cancelled']);
    WorkOrder::factory()->create([...$base, 'work_order_type' => 'preventive', 'status' => 'closed', 'completed_at' => now()->subMonths(18)]);
    WorkOrder::factory()->create([...$base, 'work_order_type' => 'improvement', 'status' => 'closed']);

    $result = service()->plannedVsCorrective($tenant->id);

    expect($result['preventive'])->toBe(4) // 3 preventive + 1 predictive
        ->and($result['corrective'])->toBe(1)
        ->and($result['total'])->toBe(5)
        ->and($result['preventive_pct'])->toBe(80.0);
});

it('plannedVsCorrective returns null percentage when no completed WOs exist', function () {
    $tenant = analyticsTenant();

    expect(service()->plannedVsCorrective($tenant->id)['preventive_pct'])->toBeNull();
});

// ── reliabilityRanking ────────────────────────────────────────────────────────

it('reliabilityRanking returns best and worst keys', function () {
    $tenant = analyticsTenant();

    $ranking = service()->reliabilityRanking($tenant->id);

    expect($ranking)->toHaveKey('best')
        ->toHaveKey('worst');
});

it('reliabilityRanking excludes KPIs with null availability_percentage', function () {
    $tenant = analyticsTenant();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => null,
    ]);

    $ranking = service()->reliabilityRanking($tenant->id);

    expect($ranking['best'])->toBeEmpty()
        ->and($ranking['worst'])->toBeEmpty();
});

it('reliabilityRanking best is sorted descending by availability', function () {
    $tenant = analyticsTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentKpi::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipA->id, 'availability_percentage' => 98.0]);
    EquipmentKpi::factory()->create(['tenant_id' => $tenant->id, 'equipment_id' => $equipB->id, 'availability_percentage' => 75.0]);

    $ranking = service()->reliabilityRanking($tenant->id);

    expect($ranking['best'][0]->value)->toBe(98.0)
        ->and($ranking['worst'][0]->value)->toBe(75.0);
});

it('reliabilityRanking enforces tenant isolation', function () {
    $tenantA = analyticsTenant();
    $tenantB = analyticsTenant();
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $tenantB->id,
        'equipment_id' => $equipB->id,
        'availability_percentage' => 99.0,
    ]);

    $ranking = service()->reliabilityRanking($tenantA->id);

    expect($ranking['best'])->toBeEmpty()
        ->and($ranking['worst'])->toBeEmpty();
});

// ── TrendPoint DTO ────────────────────────────────────────────────────────────

it('TrendPoint stores label, value and count correctly', function () {
    $point = new TrendPoint(label: 'Jun 2025', value: 42.5, count: 3);

    expect($point->label)->toBe('Jun 2025')
        ->and($point->value)->toBe(42.5)
        ->and($point->count)->toBe(3);
});

it('TrendPoint value can be null for gap months', function () {
    $point = new TrendPoint(label: 'Jun 2025', value: null);

    expect($point->value)->toBeNull()
        ->and($point->count)->toBe(0);
});
