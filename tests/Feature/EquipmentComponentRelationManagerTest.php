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

// ── El bug: «Horas de vida» ahora se ancla y avanza sola ─────────────────────

it('anchors worked_hours to the equipment meter when creating from the panel', function () {
    $this->equipment->update(['accumulated_meter_reading' => 2000]);

    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'Actuadores',
            'worked_hours' => 200,
        ])
        ->assertHasNoActionErrors();

    $component = EquipmentComponent::where('name', 'Actuadores')->first();

    expect($component->worked_hours)->toBe(200.0)
        ->and($component->meter_reading_baseline)->toBe(2000.0);
});

it('rebaselines worked_hours when it is edited from the panel', function () {
    $component = EquipmentComponent::factory()->forEquipment($this->equipment)->create([
        'name' => 'Transmisión y sellado',
        'worked_hours' => 4500,
        'meter_reading_baseline' => 6000,
    ]);
    $this->equipment->update(['accumulated_meter_reading' => 8000]);

    // El técnico reemplazó la pieza y corrige el contador a 0.
    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment->refresh(),
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('edit')->table($component), data: [
            'name' => $component->name,
            'criticality' => $component->criticality->value,
            'status' => $component->status->value,
            'worked_hours' => 0,
        ])
        ->assertHasNoActionErrors();

    expect($component->refresh()->worked_hours)->toBe(0.0)
        ->and($component->meter_reading_baseline)->toBe(8000.0);
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

// ── «Componentes» ahora se llama «Piezas» ────────────────────────────────────

it('labels the create action as registrar pieza, not the raw model name', function () {
    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])->assertSee('Registrar pieza');
});

it('does not fall back to the raw model name on the empty state', function () {
    // Sin piezas, Filament arma un estado vacío a partir del nombre crudo del
    // modelo si nadie lo dice explícito: «Cree un equipment component para
    // empezar». El mismo defecto bilingüe que tenía el botón de crear, solo que
    // este no se ve hasta que la tabla está vacía.
    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->assertSee('Sin piezas registradas')
        ->assertDontSee('equipment component');
});

// ── La tabla no se desborda con varios planes activos ────────────────────────

it('shows only the most urgent plan in the cell, with the rest behind a count', function () {
    $component = EquipmentComponent::factory()->forEquipment($this->equipment)->create(['name' => 'Actuadores']);
    $this->equipment->update(['accumulated_meter_reading' => 0]);

    // Tres planes activos a la vez: el caso real que desbordaba la fila.
    foreach ([['Cambio de aceite', 250], ['Revisión de sellos', 800], ['Calibración', 2000]] as [$name, $interval]) {
        $plan = MaintenancePlan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'equipment_component_id' => $component->id,
            'name' => $name,
            'trigger_source' => MaintenanceTriggerSource::Meter->value,
            'meter_interval' => $interval,
            'is_active' => true,
        ]);
        MaintenanceSchedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'maintenance_plan_id' => $plan->id,
            'next_due_at' => null,
            'next_due_meter' => $interval,
        ]);
    }

    // El más urgente (250h) se ve en la celda, con el contador de los otros dos.
    // El resto vive en el tooltip —también presente en el HTML, solo que no como
    // el texto principal de la celda— así que no se afirma su ausencia, solo que
    // el resumen corto es lo que encabeza la fila.
    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $this->equipment->refresh(),
        'pageClass' => EditEquipment::class,
    ])
        ->assertSee('Cambio de aceite: 250 h')
        ->assertSee('(+2 más)');
});
