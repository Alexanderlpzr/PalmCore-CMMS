<?php

use App\Domain\Analytics\Services\ExecutiveDashboardService;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentKpi;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;

/**
 * Extraído de ExecutiveDashboardController — antes la consulta vivía solo ahí,
 * sin una sola prueba. Al moverla a un servicio (para que la página "Resumen
 * Ejecutivo" de Filament reutilice los mismos números que ya consumía la API)
 * es el momento de dejarla cubierta.
 */
beforeEach(function (): void {
    $this->service = app(ExecutiveDashboardService::class);
    $this->tenant = Tenant::factory()->create();
});

// ── summary() ─────────────────────────────────────────────────────────────────

it('averages availability, mtbf and mttr across the tenant fresh KPIs', function (): void {
    $equipA = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipA->id,
        'availability_percentage' => 90.00,
        'mtbf_hours' => 100.00,
        'mttr_hours' => 4.00,
        'is_stale' => false,
    ]);
    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipB->id,
        'availability_percentage' => 80.00,
        'mtbf_hours' => 60.00,
        'mttr_hours' => 6.00,
        'is_stale' => false,
    ]);

    $summary = $this->service->summary($this->tenant->id);

    expect($summary['availability'])->toBe(85.0)
        ->and($summary['mtbf_hours'])->toBe(80.0)
        ->and($summary['mttr_hours'])->toBe(5.0);
});

it('falls back to all KPIs when none are fresh', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 77.00,
        'is_stale' => true,
    ]);

    $summary = $this->service->summary($this->tenant->id);

    expect($summary['availability'])->toBe(77.0);
});

it('counts open work orders and overdue preventives in the summary', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Preventive->value,
        'status' => WorkOrderStatus::Planned->value,
        'planned_end_at' => now()->subDay(),
    ]);

    $summary = $this->service->summary($this->tenant->id);

    expect($summary['open_work_orders'])->toBe(2)
        ->and($summary['overdue_preventives'])->toBe(1);
});

it('sums monthly cost only from work orders completed this month', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'completed_at' => now(),
        'actual_cost_total' => 500,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'completed_at' => now()->subMonths(2),
        'actual_cost_total' => 999,
    ]);

    $summary = $this->service->summary($this->tenant->id);

    expect($summary['monthly_cost'])->toBe(500.0);
});

// ── areas() ───────────────────────────────────────────────────────────────────

it('aggregates availability, failures and cost per area', function (): void {
    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $plant->id]);
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'area_id' => $area->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 88.00,
        'failure_count' => 3,
        'mttr_hours' => 5.00,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'area_id' => $area->id,
        'completed_at' => now(),
        'actual_cost_total' => 250,
    ]);

    $areas = $this->service->areas($this->tenant->id);

    expect($areas)->toHaveCount(1)
        ->and($areas[0]['code'])->toBe($area->code)
        ->and($areas[0]['availability'])->toBe(88.0)
        ->and($areas[0]['failure_count'])->toBe(3)
        ->and($areas[0]['monthly_cost'])->toBe(250.0);
});

// ── topEquipment() ────────────────────────────────────────────────────────────

it('ranks top equipment by failure count with its monthly cost', function (): void {
    $equipA = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipB = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    EquipmentKpi::factory()->create(['tenant_id' => $this->tenant->id, 'equipment_id' => $equipA->id, 'failure_count' => 5]);
    EquipmentKpi::factory()->create(['tenant_id' => $this->tenant->id, 'equipment_id' => $equipB->id, 'failure_count' => 1]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipA->id,
        'completed_at' => now(),
        'actual_cost_total' => 1000,
    ]);

    $top = $this->service->topEquipment($this->tenant->id);

    expect($top[0]['id'])->toBe($equipA->id)
        ->and($top[0]['failure_count'])->toBe(5)
        ->and($top[0]['monthly_cost'])->toBe(1000.0);
});

// ── costs() ───────────────────────────────────────────────────────────────────

it('groups this month cost by corrective, preventive, predictive and other', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach ([
        [WorkOrderType::Corrective->value, 100],
        [WorkOrderType::Emergency->value, 50],
        [WorkOrderType::Preventive->value, 200],
        [WorkOrderType::Predictive->value, 30],
        [WorkOrderType::Improvement->value, 20],
    ] as [$type, $cost]) {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'work_order_type' => $type,
            'completed_at' => now(),
            'actual_cost_total' => $cost,
        ]);
    }

    $costs = $this->service->costs($this->tenant->id);

    // Corrective + Emergency se suman como un solo balde "correctivo".
    expect($costs['corrective'])->toBe(150.0)
        ->and($costs['preventive'])->toBe(200.0)
        ->and($costs['predictive'])->toBe(30.0)
        ->and($costs['other'])->toBe(20.0)
        ->and($costs['total'])->toBe(400.0);
});

// ── trends() ──────────────────────────────────────────────────────────────────

it('returns 12 months of trend data, oldest first, with gaps as null KPIs', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 92.00,
        'period_start' => Carbon::now()->startOfMonth(),
    ]);

    $trends = $this->service->trends($this->tenant->id);

    expect($trends)->toHaveCount(12)
        ->and($trends[11]['month'])->toBe(Carbon::now()->format('Y-m'))
        ->and($trends[11]['availability'])->toBe(92.0)
        ->and($trends[0]['availability'])->toBeNull();
});

// ── Tenant scoping ────────────────────────────────────────────────────────────

it('never mixes data from another tenant', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $otherTenant->id]);
    EquipmentKpi::factory()->create([
        'tenant_id' => $otherTenant->id,
        'equipment_id' => $otherEquipment->id,
        'availability_percentage' => 10.00,
    ]);

    $summary = $this->service->summary($this->tenant->id);

    expect($summary['availability'])->toBe(0.0);
});

// ── Period filter — el "Resumen Ejecutivo" solo puede filtrar honestamente
//    las cifras de costo (historial real por mes en work_orders.completed_at);
//    disponibilidad/MTBF/MTTR vienen de equipment_kpis, una foto del estado
//    actual (una fila por equipo), y deben ignorar el período pedido. ─────────

it('scopes costs() to the explicit period instead of the current month', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Corrective->value,
        'completed_at' => Carbon::create(2026, 3, 15),
        'actual_cost_total' => 700,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'completed_at' => now(),
        'actual_cost_total' => 999,
    ]);

    $costs = $this->service->costs(
        $this->tenant->id,
        Carbon::create(2026, 3, 1),
        Carbon::create(2026, 3, 1),
    );

    expect($costs['total'])->toBe(700.0)
        ->and($costs['period_start'])->toBe('2026-03-01')
        ->and($costs['period_end'])->toBe('2026-03-31');
});

it('scopes summary() monthly_cost to the explicit period but leaves KPI averages untouched', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 95.00,
        'is_stale' => false,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'completed_at' => Carbon::create(2026, 3, 15),
        'actual_cost_total' => 700,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'completed_at' => now(),
        'actual_cost_total' => 999,
    ]);

    $unfiltered = $this->service->summary($this->tenant->id);
    $filtered = $this->service->summary(
        $this->tenant->id,
        Carbon::create(2026, 3, 1),
        Carbon::create(2026, 3, 1),
    );

    expect($filtered['monthly_cost'])->toBe(700.0)
        ->and($filtered['availability'])->toBe($unfiltered['availability'])
        ->and($filtered['availability'])->toBe(95.0);
});

it('scopes areas() and topEquipment() monthly_cost to the explicit period', function (): void {
    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $plant->id]);
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'area_id' => $area->id]);

    EquipmentKpi::factory()->create(['tenant_id' => $this->tenant->id, 'equipment_id' => $equipment->id, 'failure_count' => 2]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'area_id' => $area->id,
        'completed_at' => Carbon::create(2026, 3, 15),
        'actual_cost_total' => 700,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'area_id' => $area->id,
        'completed_at' => now(),
        'actual_cost_total' => 999,
    ]);

    $from = Carbon::create(2026, 3, 1);
    $to = Carbon::create(2026, 3, 1);

    $areas = $this->service->areas($this->tenant->id, $from, $to);
    $top = $this->service->topEquipment($this->tenant->id, $from, $to);

    expect($areas[0]['monthly_cost'])->toBe(700.0)
        ->and($top[0]['monthly_cost'])->toBe(700.0);
});

// ── costTrend() ───────────────────────────────────────────────────────────────

it('builds a month-by-month cost series bounded to the requested range', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'completed_at' => Carbon::create(2026, 2, 10),
        'actual_cost_total' => 100,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'completed_at' => Carbon::create(2026, 4, 10),
        'actual_cost_total' => 50,
    ]);

    $trend = $this->service->costTrend(
        $this->tenant->id,
        Carbon::create(2026, 1, 1),
        Carbon::create(2026, 4, 1),
    );

    expect($trend)->toHaveCount(4)
        ->and($trend[0]['month'])->toBe('2026-01')
        ->and($trend[1]['cost'])->toBe(100.0)
        ->and($trend[3]['cost'])->toBe(50.0);
});

it('defaults costTrend() to the trailing 12 months when no range is given', function (): void {
    $trend = $this->service->costTrend($this->tenant->id);

    expect($trend)->toHaveCount(12)
        ->and($trend[11]['month'])->toBe(Carbon::now()->format('Y-m'));
});
