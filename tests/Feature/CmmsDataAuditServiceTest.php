<?php

use App\Domain\Analytics\DTOs\AuditFinding;
use App\Domain\Analytics\Enums\AuditSeverity;
use App\Domain\Analytics\Services\CmmsDataAuditService;
use App\Domain\Assets\Enums\EquipmentCriticality;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

function audit(string $tenantId): array
{
    return app(CmmsDataAuditService::class)->run($tenantId);
}

function finding(array $findings, string $key): ?AuditFinding
{
    return collect($findings)->firstWhere('key', $key);
}

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

// ── Cada chequeo detecta su hueco ────────────────────────────────────────────

it('flags a critical equipment with no maintenance plan', function (): void {
    Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'criticality' => EquipmentCriticality::Critical->value,
        'code' => 'PRENSA-01',
    ]);

    $f = finding(audit($this->tenant->id), 'critical_equipment_without_plan');

    expect($f)->not->toBeNull()
        ->and($f->severity)->toBe(AuditSeverity::Critical)
        ->and($f->count)->toBe(1)
        ->and($f->sample[0])->toContain('PRENSA-01');
});

it('does not flag a critical equipment that already has a plan', function (): void {
    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'criticality' => EquipmentCriticality::Critical->value,
    ]);
    MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
    ]);

    expect(finding(audit($this->tenant->id), 'critical_equipment_without_plan'))->toBeNull();
});

it('flags an overdue plan that has no open work order', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 600]);
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 500, // acumulado 600 ≥ 500 → vencido
    ]);

    $f = finding(audit($this->tenant->id), 'overdue_plans_without_work_order');

    expect($f)->not->toBeNull()
        ->and($f->count)->toBe(1);
});

it('does not flag an overdue plan while it has an open work order', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'accumulated_meter_reading' => 600]);
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 500,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'maintenance_plan_id' => $plan->id,
        'status' => WorkOrderStatus::Planned->value,
    ]);

    expect(finding(audit($this->tenant->id), 'overdue_plans_without_work_order'))->toBeNull();
});

it('flags an active meter plan that never got a due meter', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 500,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => null,
    ]);

    expect(finding(audit($this->tenant->id), 'meter_plans_never_activated'))->not->toBeNull();
});

it('flags a work order stuck in progress for over a month', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    $wo = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'status' => WorkOrderStatus::InProgress->value,
    ]);
    // El factory pone updated_at = ahora; lo empujamos al pasado sin tocar el modelo.
    DB::table('work_orders')->where('id', $wo->id)->update(['updated_at' => now()->subDays(40)]);

    $f = finding(audit($this->tenant->id), 'stuck_work_orders');

    expect($f)->not->toBeNull()
        ->and($f->severity)->toBe(AuditSeverity::Warning);
});

it('flags a component that worked past its useful life', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    EquipmentComponent::factory()->forEquipment($equipment)->create([
        'name' => 'Rodamiento',
        'status' => 'active',
        'useful_life_hours' => 5000,
        'worked_hours' => 5200,
    ]);

    expect(finding(audit($this->tenant->id), 'components_past_useful_life'))->not->toBeNull();
});

it('flags a preventive work order with no plan behind it', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Preventive->value,
        'maintenance_plan_id' => null,
    ]);

    $f = finding(audit($this->tenant->id), 'preventive_work_orders_without_plan');

    expect($f)->not->toBeNull()
        ->and($f->severity)->toBe(AuditSeverity::Info);
});

// ── Sano y aislado ───────────────────────────────────────────────────────────

it('returns nothing for a tenant with no data problems', function (): void {
    expect(audit($this->tenant->id))->toBe([]);
});

it('never reports another tenant problems', function (): void {
    $other = Tenant::factory()->create();
    Equipment::factory()->create([
        'tenant_id' => $other->id,
        'is_active' => true,
        'criticality' => EquipmentCriticality::Critical->value,
    ]);

    expect(audit($this->tenant->id))->toBe([]);
});

it('orders findings with the critical ones first', function (): void {
    // Un crítico (equipo sin plan) y un informativo (preventivo sin plan).
    Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'criticality' => EquipmentCriticality::Critical->value,
    ]);
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => WorkOrderType::Preventive->value,
        'maintenance_plan_id' => null,
    ]);

    $findings = audit($this->tenant->id);

    expect($findings[0]->severity)->toBe(AuditSeverity::Critical)
        ->and(collect($findings)->last()->severity)->toBe(AuditSeverity::Info);
});
