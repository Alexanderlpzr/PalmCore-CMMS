<?php

use App\Domain\Analytics\Services\MaintenanceCostReportService;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\MaintenanceBudget;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\WorkOrder;

beforeEach(function (): void {
    $this->service = app(MaintenanceCostReportService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
});

function completedWo(array $overrides = []): WorkOrder
{
    return WorkOrder::factory()->create(array_merge([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'equipment_id' => test()->equipment->id,
        'completed_at' => now(),
    ], $overrides));
}

// ── Totales y desglose ────────────────────────────────────────────────────────

it('sums the three cost components into the month total', function (): void {
    completedWo(['actual_cost_labor' => 100, 'actual_cost_parts' => 200, 'actual_cost_external' => 50, 'actual_cost_total' => 350]);
    completedWo(['actual_cost_labor' => 10, 'actual_cost_parts' => 0, 'actual_cost_external' => 40, 'actual_cost_total' => 50]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['labor'])->toBe(110.0)
        ->and($report['parts'])->toBe(200.0)
        ->and($report['external'])->toBe(90.0)
        ->and($report['total'])->toBe(400.0)
        ->and($report['work_order_count'])->toBe(2);
});

it('the breakdown always adds up to the total', function (): void {
    completedWo(['actual_cost_labor' => 123.45, 'actual_cost_parts' => 67.89, 'actual_cost_external' => 10.11, 'actual_cost_total' => 201.45]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect(round($report['labor'] + $report['parts'] + $report['external'], 2))->toBe($report['total']);
});

it('groups spend by type, folding emergency into corrective', function (): void {
    completedWo(['work_order_type' => WorkOrderType::Corrective->value, 'actual_cost_labor' => 100, 'actual_cost_total' => 100]);
    completedWo(['work_order_type' => WorkOrderType::Emergency->value, 'actual_cost_parts' => 50, 'actual_cost_total' => 50]);
    completedWo(['work_order_type' => WorkOrderType::Preventive->value, 'actual_cost_labor' => 80, 'actual_cost_total' => 80]);
    completedWo(['work_order_type' => WorkOrderType::Predictive->value, 'actual_cost_labor' => 30, 'actual_cost_total' => 30]);
    completedWo(['work_order_type' => WorkOrderType::Improvement->value, 'actual_cost_labor' => 20, 'actual_cost_total' => 20]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['by_type']['corrective'])->toBe(150.0)
        ->and($report['by_type']['preventive'])->toBe(80.0)
        ->and($report['by_type']['predictive'])->toBe(30.0)
        ->and($report['by_type']['other'])->toBe(20.0);
});

// ── La fecha que ancla el gasto ───────────────────────────────────────────────

it('recognizes spend in the month the work was completed, not created', function (): void {
    // Creada el mes pasado, completada este mes: es gasto de este mes.
    completedWo([
        'created_at' => now()->subMonth(),
        'completed_at' => now(),
        'actual_cost_labor' => 500,
        'actual_cost_total' => 500,
    ]);
    // Completada el mes pasado: no cuenta este mes.
    completedWo([
        'completed_at' => now()->subMonth(),
        'actual_cost_labor' => 999,
        'actual_cost_total' => 999,
    ]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['total'])->toBe(500.0);
});

it('ignores work orders that are not completed yet', function (): void {
    completedWo(['completed_at' => null, 'actual_cost_labor' => 777, 'actual_cost_total' => 777]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['total'])->toBe(0.0)
        ->and($report['work_order_count'])->toBe(0);
});

// ── Presupuesto ───────────────────────────────────────────────────────────────

it('compares spend against the assigned budget', function (): void {
    completedWo(['actual_cost_labor' => 3000, 'actual_cost_total' => 3000]);

    MaintenanceBudget::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => (int) now()->year,
        'month' => (int) now()->month,
        'amount' => 10000,
    ]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['budget'])->toBe(10000.0)
        ->and($report['remaining'])->toBe(7000.0)
        ->and($report['percent_used'])->toBe(30.0)
        ->and($report['is_over_budget'])->toBeFalse();
});

it('flags when spend goes over the budget', function (): void {
    completedWo(['actual_cost_labor' => 12000, 'actual_cost_total' => 12000]);

    MaintenanceBudget::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => (int) now()->year,
        'month' => (int) now()->month,
        'amount' => 10000,
    ]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['remaining'])->toBe(-2000.0)
        ->and($report['is_over_budget'])->toBeTrue();
});

it('returns null budget figures when no budget was set', function (): void {
    completedWo(['actual_cost_labor' => 500, 'actual_cost_total' => 500]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['budget'])->toBeNull()
        ->and($report['remaining'])->toBeNull()
        ->and($report['percent_used'])->toBeNull()
        ->and($report['is_over_budget'])->toBeFalse();
});

// ── Aislamiento ───────────────────────────────────────────────────────────────

it('never counts spend from another plant or tenant', function (): void {
    $otherPlant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $otherPlant->id]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $otherPlant->id,
        'equipment_id' => $otherEquipment->id,
        'completed_at' => now(),
        'actual_cost_labor' => 999,
        'actual_cost_total' => 999,
    ]);

    completedWo(['actual_cost_labor' => 100, 'actual_cost_total' => 100]);

    $report = $this->service->monthlyReport($this->tenant->id, $this->plant->id, (int) now()->year, (int) now()->month);

    expect($report['total'])->toBe(100.0);
});
