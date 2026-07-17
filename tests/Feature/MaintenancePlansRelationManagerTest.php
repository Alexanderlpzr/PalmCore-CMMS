<?php

use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\RelationManagers\MaintenancePlansRelationManager;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * Antes de esto, un plan creado desde «Programar mantenimiento» en una pieza no
 * tenía dónde editarse ni borrarse sin salir a buscarlo entre todos los planes de
 * todos los equipos. Esta pestaña —«Planes de mantenimiento», dentro del propio
 * equipo— es el lugar que faltaba.
 */
beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->equipment = Equipment::factory()->for($this->tenant)->create();
});

it('lists every plan that belongs to the equipment, piece-scoped or not', function () {
    $component = EquipmentComponent::factory()->forEquipment($this->equipment)->create(['name' => 'Rodamiento']);

    MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => null,
        'name' => 'Revisión general',
    ]);
    MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $component->id,
        'name' => 'Cambio de rodamiento',
    ]);

    Livewire::test(MaintenancePlansRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->assertSee('Revisión general')
        ->assertSee('Cambio de rodamiento')
        ->assertSee('Rodamiento'); // la pieza a la que pertenece, en su columna
});

it('never lists a plan that belongs to a different equipment', function () {
    $otherEquipment = Equipment::factory()->for($this->tenant)->create();
    MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $otherEquipment->id,
        'name' => 'Plan de otro equipo',
    ]);

    Livewire::test(MaintenancePlansRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])->assertDontSee('Plan de otro equipo');
});

it('edits a plan from within the equipment it belongs to', function () {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'name' => 'Nombre original',
        'trigger_source' => MaintenanceTriggerSource::Manual->value,
    ]);

    Livewire::test(MaintenancePlansRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('edit')->table($plan), data: [
            'equipment_id' => $this->equipment->id,
            'name' => 'Nombre corregido',
            'trigger_source' => MaintenanceTriggerSource::Manual->value,
        ])
        ->assertHasNoActionErrors();

    expect($plan->refresh()->name)->toBe('Nombre corregido');
});

it('deletes a plan without deleting the work orders it already generated', function () {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    Livewire::test(MaintenancePlansRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('delete')->table($plan))
        ->assertHasNoActionErrors();

    // Borrado suave: el plan desaparece de la lista, pero el registro sigue
    // existiendo como historial — nullOnDelete es lo que protege las OTs que ya
    // generó, no un borrado duro.
    expect(MaintenancePlan::find($plan->id))->toBeNull()
        ->and(MaintenancePlan::withTrashed()->find($plan->id))->not->toBeNull();
});

it('shows the plan count as a badge', function () {
    MaintenancePlan::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    expect(MaintenancePlansRelationManager::getBadge($this->equipment, EditEquipment::class))->toBe('3');
});

it('shows no badge when the equipment has no plans yet', function () {
    expect(MaintenancePlansRelationManager::getBadge($this->equipment, EditEquipment::class))->toBeNull();
});

// ── «Registrar ejecución»: ya se hizo, sin pasar por una OT ─────────────────────

it('registers a manual execution and advances the plan meter', function () {
    $this->equipment->update(['accumulated_meter_reading' => 850]);

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 1000,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 1000,
        'times_executed' => 0,
    ]);

    Livewire::test(MaintenancePlansRelationManager::class, [
        'ownerRecord' => $this->equipment->refresh(),
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('registerManualExecution')->table($plan), data: [
            'completed_at' => now()->toDateTimeString(),
            'completed_meter' => 850,
        ])
        ->assertHasNoActionErrors();

    expect($plan->schedule->refresh()->times_executed)->toBe(1)
        ->and($plan->schedule->next_due_meter)->toBe(1850.0)
        ->and($plan->schedule->last_work_order_id)->toBeNull();
});

it('refuses to register a manual execution while the plan already has an open work order', function () {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 1000,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 1000,
        'times_executed' => 0,
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'maintenance_plan_id' => $plan->id,
        'work_order_type' => WorkOrderType::Preventive->value,
        'status' => WorkOrderStatus::Planned->value,
    ]);

    Livewire::test(MaintenancePlansRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])->callAction(TestAction::make('registerManualExecution')->table($plan), data: [
        'completed_at' => now()->toDateTimeString(),
        'completed_meter' => 500,
    ]);

    // No se duplicó: la ejecución abierta sigue siendo la única fuente de verdad.
    expect($plan->schedule->refresh()->times_executed)->toBe(0);
});

it('does not offer registerManualExecution on an inactive plan', function () {
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'is_active' => false,
    ]);

    Livewire::test(MaintenancePlansRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])->assertActionHidden(TestAction::make('registerManualExecution')->table($plan));
});
