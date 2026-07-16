<?php

use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\RelationManagers\ComponentsRelationManager;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceSchedule;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

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

it('creates a component with tenant_id filled from the current Filament tenant', function () {
    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('create')->table(), data: ['name' => 'Motor'])
        ->assertHasNoActionErrors();

    $component = EquipmentComponent::where('name', 'Motor')->first();

    expect($component)->not->toBeNull()
        ->and($component->tenant_id)->toBe($this->tenant->id)
        ->and($component->equipment_id)->toBe($this->equipment->id);
});

it('schedules a component-scoped meter plan from the row action, already active', function () {
    $component = EquipmentComponent::factory()->forEquipment($this->equipment)->create([
        'name' => 'Unidad de potencia',
    ]);

    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(
            TestAction::make('scheduleMaintenance')->table($component),
            data: [
                'name' => 'Cambio de aceite',
                'trigger_source' => MaintenanceTriggerSource::Meter->value,
                'meter_interval' => 5000,
                'meter_lead_hours' => 200,
            ],
        )
        ->assertHasNoActionErrors();

    $plan = MaintenancePlan::where('name', 'Cambio de aceite')->first();

    expect($plan)->not->toBeNull()
        ->and($plan->equipment_id)->toBe($this->equipment->id)
        ->and($plan->equipment_component_id)->toBe($component->id)
        ->and($plan->meter_interval)->toBe(5000)
        ->and($plan->meter_lead_hours)->toBe(200)
        ->and($plan->is_active)->toBeTrue()
        // Activado = tiene un vencimiento; sin esto no generaría ninguna OT.
        ->and($plan->schedule->next_due_meter)->not->toBeNull();
});

it('shows the remaining hours at a glance for a component with a meter plan', function () {
    $component = EquipmentComponent::factory()->forEquipment($this->equipment)->create(['name' => 'Bomba']);
    $this->equipment->update(['accumulated_meter_reading' => 4750]);

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $component->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 5000,
        'meter_lead_hours' => 200,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 5000,
    ]);

    // Acumulado 4750, meta 5000 → faltan 250 h.
    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment->refresh(),
        'pageClass' => EditEquipment::class,
    ])->assertSee('250 h');
});

it('marks an overdue component meter plan as vencido', function () {
    $component = EquipmentComponent::factory()->forEquipment($this->equipment)->create(['name' => 'Rodamiento']);
    $this->equipment->update(['accumulated_meter_reading' => 5300]);

    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'equipment_component_id' => $component->id,
        'trigger_source' => MaintenanceTriggerSource::Meter->value,
        'meter_interval' => 5000,
        'is_active' => true,
    ]);
    MaintenanceSchedule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'maintenance_plan_id' => $plan->id,
        'next_due_at' => null,
        'next_due_meter' => 5000,
    ]);

    // Acumulado 5300 > meta 5000 → vencido.
    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment->refresh(),
        'pageClass' => EditEquipment::class,
    ])->assertSee('Vencido');
});

it('schedules a component-scoped date plan from the row action', function () {
    $component = EquipmentComponent::factory()->forEquipment($this->equipment)->create([
        'name' => 'Filtro',
    ]);

    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(
            TestAction::make('scheduleMaintenance')->table($component),
            data: [
                'name' => 'Cambio de filtro',
                'trigger_source' => MaintenanceTriggerSource::Calendar->value,
                'time_frequency' => 'monthly',
            ],
        )
        ->assertHasNoActionErrors();

    $plan = MaintenancePlan::where('name', 'Cambio de filtro')->first();

    expect($plan)->not->toBeNull()
        ->and($plan->equipment_component_id)->toBe($component->id)
        ->and($plan->is_active)->toBeTrue()
        ->and($plan->schedule->next_due_at)->not->toBeNull();
});
