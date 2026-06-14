<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Jobs\RecalculateEquipmentKpisJob;
use App\Models\Equipment;
use App\Models\EquipmentKpi;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Bus;

// ── Helpers ───────────────────────────────────────────────────────────────────

function woWithEquipment(): WorkOrder
{
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    return WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);
}

// ── Dispatch on terminal status ───────────────────────────────────────────────

it('dispatches job when WorkOrder transitions to Completed', function () {
    Bus::fake();

    $workOrder = woWithEquipment();

    $workOrder->update(['status' => WorkOrderStatus::Completed->value]);

    Bus::assertDispatched(RecalculateEquipmentKpisJob::class, function ($job) use ($workOrder) {
        return $job->equipmentId === $workOrder->equipment_id;
    });
});

it('dispatches job when WorkOrder transitions to Closed', function () {
    Bus::fake();

    $workOrder = woWithEquipment();

    $workOrder->update(['status' => WorkOrderStatus::Closed->value]);

    Bus::assertDispatched(RecalculateEquipmentKpisJob::class, function ($job) use ($workOrder) {
        return $job->equipmentId === $workOrder->equipment_id;
    });
});

// ── No dispatch on other status changes ───────────────────────────────────────

it('does not dispatch job when WorkOrder transitions to InProgress', function () {
    Bus::fake();

    $workOrder = woWithEquipment();
    $workOrder->update(['status' => WorkOrderStatus::Planned->value]);

    $workOrder->update(['status' => WorkOrderStatus::InProgress->value]);

    Bus::assertNotDispatched(RecalculateEquipmentKpisJob::class);
});

it('does not dispatch job when only non-status fields change', function () {
    Bus::fake();

    $workOrder = woWithEquipment();

    $workOrder->update(['title' => 'Updated title']);

    Bus::assertNotDispatched(RecalculateEquipmentKpisJob::class);
});

// ── markStale is called ───────────────────────────────────────────────────────

it('marks KPI stale when WorkOrder transitions to Completed', function () {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'is_stale' => false,
    ]);

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);

    Bus::fake();

    $workOrder->update(['status' => WorkOrderStatus::Completed->value]);

    expect(EquipmentKpi::withoutGlobalScopes()
        ->where('equipment_id', $equipment->id)
        ->value('is_stale')
    )->toBeTrue();
});

it('marks KPI stale when WorkOrder transitions to Closed', function () {
    $tenant = Tenant::factory()->create();
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'is_stale' => false,
    ]);

    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);

    Bus::fake();

    $workOrder->update(['status' => WorkOrderStatus::Closed->value]);

    expect(EquipmentKpi::withoutGlobalScopes()
        ->where('equipment_id', $equipment->id)
        ->value('is_stale')
    )->toBeTrue();
});
