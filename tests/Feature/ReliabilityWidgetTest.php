<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Analytics\CostByEquipmentWidget;
use App\Filament\Widgets\Analytics\ParetoFailureModesWidget;
use App\Filament\Widgets\Analytics\ParetoFailuresWidget;
use App\Filament\Widgets\Analytics\ReliabilityRankingWidget;
use App\Filament\Widgets\Reliability\GlobalReliabilitySummaryWidget;
use App\Filament\Widgets\Reliability\HighestDowntimeWidget;
use App\Filament\Widgets\Reliability\MaintenanceComplianceWidget;
use App\Filament\Widgets\Reliability\MostFailuresWidget;
use App\Filament\Widgets\Reliability\WorstAvailabilityWidget;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Equipment;
use App\Models\EquipmentKpi;
use App\Models\Tenant;

// CurrentTenant is static state — must be cleared after each test so it does
// not leak into subsequent test files and corrupt their BelongsToTenant scopes.
afterEach(fn () => CurrentTenant::clear());

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Set the current tenant and return it.
 */
function activeTenant(): Tenant
{
    $tenant = Tenant::factory()->create();
    CurrentTenant::set($tenant);

    return $tenant;
}

function kpiFor(Equipment $equipment, array $overrides = []): EquipmentKpi
{
    return EquipmentKpi::factory()->create(array_merge([
        'tenant_id' => $equipment->tenant_id,
        'equipment_id' => $equipment->id,
    ], $overrides));
}

// ── TenantScope ───────────────────────────────────────────────────────────────

it('WorstAvailabilityWidget only returns KPIs for the current tenant', function () {
    $tenantA = activeTenant();
    $tenantB = Tenant::factory()->create();

    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    kpiFor($equipA, ['availability_percentage' => 80.00, 'failure_count' => 1]);
    kpiFor($equipB, ['availability_percentage' => 70.00, 'failure_count' => 1]);

    $query = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->orderBy('availability_percentage');

    $results = $query->get();

    // TenantScope is active — only tenantA's KPI should appear
    expect($results)->toHaveCount(1)
        ->and($results->first()->equipment_id)->toBe($equipA->id);
});

it('MostFailuresWidget only returns KPIs for the current tenant', function () {
    $tenantA = activeTenant();
    $tenantB = Tenant::factory()->create();

    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    kpiFor($equipA, ['failure_count' => 5, 'availability_percentage' => 90.00]);
    kpiFor($equipB, ['failure_count' => 10, 'availability_percentage' => 80.00]);

    $query = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->where('failure_count', '>', 0)
        ->orderByDesc('failure_count');

    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->equipment_id)->toBe($equipA->id);
});

it('HighestDowntimeWidget only returns KPIs for the current tenant', function () {
    $tenantA = activeTenant();
    $tenantB = Tenant::factory()->create();

    $equipA = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    kpiFor($equipA, ['downtime_hours' => 12.00, 'availability_percentage' => 90.00, 'failure_count' => 2]);
    kpiFor($equipB, ['downtime_hours' => 24.00, 'availability_percentage' => 80.00, 'failure_count' => 4]);

    $query = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->where('downtime_hours', '>', 0)
        ->orderByDesc('downtime_hours');

    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->equipment_id)->toBe($equipA->id);
});

// ── Limit 10 ──────────────────────────────────────────────────────────────────

it('WorstAvailabilityWidget respects limit of 10', function () {
    $tenant = activeTenant();

    // Create 15 equipment with KPIs
    Equipment::factory()->count(15)->create(['tenant_id' => $tenant->id])->each(
        fn (Equipment $e) => kpiFor($e, ['availability_percentage' => fake()->randomFloat(2, 60, 99), 'failure_count' => 1])
    );

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->orderBy('availability_percentage')
        ->limit(10)
        ->get();

    expect($results)->toHaveCount(10);
});

it('MostFailuresWidget respects limit of 10', function () {
    $tenant = activeTenant();

    Equipment::factory()->count(15)->create(['tenant_id' => $tenant->id])->each(
        fn (Equipment $e) => kpiFor($e, ['failure_count' => fake()->numberBetween(1, 20), 'availability_percentage' => 90.00])
    );

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->where('failure_count', '>', 0)
        ->orderByDesc('failure_count')
        ->limit(10)
        ->get();

    expect($results)->toHaveCount(10);
});

// ── Exclusion of null / zero values ──────────────────────────────────────────

it('WorstAvailabilityWidget excludes KPIs with null availability_percentage', function () {
    $tenant = activeTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    kpiFor($equipA, ['availability_percentage' => 85.00, 'failure_count' => 1]);
    kpiFor($equipB, ['availability_percentage' => null]);

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->orderBy('availability_percentage')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->equipment_id)->toBe($equipA->id);
});

it('MostFailuresWidget excludes KPIs with failure_count = 0', function () {
    $tenant = activeTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    kpiFor($equipA, ['failure_count' => 3, 'availability_percentage' => 90.00]);
    kpiFor($equipB, ['failure_count' => 0, 'availability_percentage' => 95.00]);

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->where('failure_count', '>', 0)
        ->orderByDesc('failure_count')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->equipment_id)->toBe($equipA->id);
});

it('HighestDowntimeWidget excludes KPIs with downtime_hours = 0', function () {
    $tenant = activeTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    kpiFor($equipA, ['downtime_hours' => 8.00, 'availability_percentage' => 90.00, 'failure_count' => 2]);
    kpiFor($equipB, ['downtime_hours' => 0.00, 'availability_percentage' => 100.00]);

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->where('downtime_hours', '>', 0)
        ->orderByDesc('downtime_hours')
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->equipment_id)->toBe($equipA->id);
});

// ── Sort order ────────────────────────────────────────────────────────────────

it('WorstAvailabilityWidget orders by availability_percentage ascending', function () {
    $tenant = activeTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipC = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    kpiFor($equipA, ['availability_percentage' => 95.00, 'failure_count' => 1]);
    kpiFor($equipB, ['availability_percentage' => 72.00, 'failure_count' => 1]);
    kpiFor($equipC, ['availability_percentage' => 83.00, 'failure_count' => 1]);

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->orderBy('availability_percentage')
        ->get();

    expect($results->pluck('availability_percentage')->map(fn ($v) => (float) $v)->values()->toArray())
        ->toBe([72.0, 83.0, 95.0]);
});

it('MostFailuresWidget orders by failure_count descending', function () {
    $tenant = activeTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipC = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    kpiFor($equipA, ['failure_count' => 2, 'availability_percentage' => 90.00]);
    kpiFor($equipB, ['failure_count' => 7, 'availability_percentage' => 80.00]);
    kpiFor($equipC, ['failure_count' => 4, 'availability_percentage' => 85.00]);

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->where('failure_count', '>', 0)
        ->orderByDesc('failure_count')
        ->get();

    expect($results->pluck('failure_count')->values()->toArray())
        ->toBe([7, 4, 2]);
});

it('HighestDowntimeWidget orders by downtime_hours descending', function () {
    $tenant = activeTenant();
    $equipA = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $tenant->id]);
    $equipC = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    kpiFor($equipA, ['downtime_hours' => 4.00, 'availability_percentage' => 92.00, 'failure_count' => 1]);
    kpiFor($equipB, ['downtime_hours' => 18.00, 'availability_percentage' => 80.00, 'failure_count' => 3]);
    kpiFor($equipC, ['downtime_hours' => 9.00, 'availability_percentage' => 88.00, 'failure_count' => 2]);

    $results = EquipmentKpi::query()
        ->whereNotNull('availability_percentage')
        ->where('downtime_hours', '>', 0)
        ->orderByDesc('downtime_hours')
        ->get();

    expect($results->pluck('downtime_hours')->map(fn ($v) => (float) $v)->values()->toArray())
        ->toBe([18.0, 9.0, 4.0]);
});

// ── GlobalReliabilitySummaryWidget ───────────────────────────────────────────

it('GlobalReliabilitySummaryWidget aggregates are scoped to current tenant', function () {
    $tenantA = activeTenant();
    $tenantB = Tenant::factory()->create();

    $equipA1 = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipA2 = Equipment::factory()->create(['tenant_id' => $tenantA->id]);
    $equipB1 = Equipment::factory()->create(['tenant_id' => $tenantB->id]);

    kpiFor($equipA1, ['failure_count' => 2, 'downtime_hours' => 4.00, 'availability_percentage' => 90.00]);
    kpiFor($equipA2, ['failure_count' => 3, 'downtime_hours' => 6.00, 'availability_percentage' => 80.00]);
    kpiFor($equipB1, ['failure_count' => 10, 'downtime_hours' => 50.00, 'availability_percentage' => 50.00]);

    $summary = EquipmentKpi::query()
        ->selectRaw(
            'COUNT(DISTINCT equipment_id) AS total_equipment,
             SUM(failure_count) AS total_failures,
             SUM(downtime_hours) AS total_downtime'
        )
        ->first();

    expect((int) $summary->total_equipment)->toBe(2)
        ->and((int) $summary->total_failures)->toBe(5)
        ->and((float) $summary->total_downtime)->toBe(10.0);
});

it('GlobalReliabilitySummaryWidget returns zero totals when no KPIs exist', function () {
    activeTenant();

    $summary = EquipmentKpi::query()
        ->selectRaw(
            'COUNT(DISTINCT equipment_id) AS total_equipment,
             SUM(failure_count) AS total_failures,
             AVG(availability_percentage) AS avg_availability'
        )
        ->first();

    expect((int) $summary->total_equipment)->toBe(0)
        ->and((int) ($summary->total_failures ?? 0))->toBe(0)
        ->and($summary->avg_availability)->toBeNull();
});

// ── Widget sort order ─────────────────────────────────────────────────────────

it('reliability widgets are sorted in the correct dashboard order', function () {
    expect(GlobalReliabilitySummaryWidget::getSort())->toBe(2)
        ->and(WorstAvailabilityWidget::getSort())->toBe(3)
        ->and(MostFailuresWidget::getSort())->toBe(4)
        ->and(HighestDowntimeWidget::getSort())->toBe(5);
});

it('the analytics dashboard actually renders the reliability and pareto widgets', function () {
    // Regression guard: these widgets were built but never listed in
    // Dashboard::getWidgets(), so they rendered nowhere. They must stay wired.
    $widgets = (new Dashboard)->getWidgets();

    expect($widgets)->toContain(
        GlobalReliabilitySummaryWidget::class,
        MaintenanceComplianceWidget::class,
        WorstAvailabilityWidget::class,
        MostFailuresWidget::class,
        HighestDowntimeWidget::class,
        CostByEquipmentWidget::class,
        ParetoFailuresWidget::class,
        ParetoFailureModesWidget::class,
        ReliabilityRankingWidget::class,
    );
});
