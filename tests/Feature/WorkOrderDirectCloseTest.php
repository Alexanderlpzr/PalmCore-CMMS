<?php

use App\Domain\Assets\Enums\EquipmentStatus;
use App\Domain\Maintenance\Enums\ExpenseCategory;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Models\Equipment;
use App\Models\MaintenanceBudgetExpense;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->actor = User::factory()->create();
});

function openWorkOrder(Tenant $tenant, array $attributes = []): WorkOrder
{
    $equipment = Equipment::factory()->create(['tenant_id' => $tenant->id]);

    return WorkOrder::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'plant_id' => $equipment->plant_id,
        'status' => WorkOrderStatus::Draft->value,
    ], $attributes));
}

it('closes an open OT directly (Abierta → Cerrada) and stamps the completion marks', function () {
    $wo = openWorkOrder($this->tenant);

    app(WorkOrderService::class)->transition($wo, WorkOrderStatus::Closed, $this->actor);

    $wo->refresh();

    expect($wo->status)->toBe(WorkOrderStatus::Closed)
        ->and($wo->closed_at)->not->toBeNull()
        ->and($wo->completed_at)->not->toBeNull()
        ->and($wo->completed_by)->toBe($this->actor->id);
});

it('returns the stopped equipment to service when the OT is closed directly', function () {
    $wo = openWorkOrder($this->tenant, ['equipment_stopped' => true]);
    $wo->equipment->update(['status' => EquipmentStatus::UnderMaintenance->value]);

    app(WorkOrderService::class)->transition($wo, WorkOrderStatus::Closed, $this->actor);

    expect($wo->equipment->fresh()->status)->toBe(EquipmentStatus::Active);
});

it('creates a single budget expense from the OT total cost when closed', function () {
    $wo = openWorkOrder($this->tenant);

    app(WorkOrderService::class)->transition($wo, WorkOrderStatus::Closed, $this->actor, [
        'work_performed' => 'Cambio de rodamiento',
        'actual_cost_total' => 350000,
    ]);

    $expenses = MaintenanceBudgetExpense::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->get();

    // Un solo gasto con el costo total de la OT, sin desglosar por concepto.
    expect($expenses)->toHaveCount(1)
        ->and($expenses->first()->amount)->toBe(350000.0)
        ->and($expenses->first()->category)->toBe(ExpenseCategory::Otros);
});

it('does not create budget expenses when the OT is closed with no costs', function () {
    $wo = openWorkOrder($this->tenant);

    app(WorkOrderService::class)->transition($wo, WorkOrderStatus::Closed, $this->actor, [
        'work_performed' => 'Inspección sin costo',
    ]);

    expect(MaintenanceBudgetExpense::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
});
