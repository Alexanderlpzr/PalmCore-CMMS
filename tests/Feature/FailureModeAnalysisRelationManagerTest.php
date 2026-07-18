<?php

use App\Domain\Maintenance\Enums\FailureConsequenceCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\MaintenanceTriggerSource;
use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\RelationManagers\FailureModeAnalysisRelationManager;
use App\Models\Equipment;
use App\Models\FailureModeAnalysis;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * El catálogo RCM-lite de modos de falla por equipo, y la acción que lo
 * convierte en algo accionable: crear la tarea de búsqueda que revela una
 * falla oculta, sin salir a la pantalla general de planes.
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

it('lists the failure mode analyses that belong to the equipment', function () {
    FailureModeAnalysis::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'failure_mode' => FailureMode::Bearing->value,
        'consequence_category' => FailureConsequenceCategory::Operational->value,
    ]);

    Livewire::test(FailureModeAnalysisRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])->assertSee('Rodamiento / cojinete');
});

it('never lists an analysis that belongs to a different equipment', function () {
    $otherEquipment = Equipment::factory()->for($this->tenant)->create();
    FailureModeAnalysis::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $otherEquipment->id,
        'failure_mode' => FailureMode::Corrosion->value,
    ]);

    Livewire::test(FailureModeAnalysisRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])->assertDontSee('Corrosión');
});

it('offers the create-failure-finding-task action only on hidden analyses without a linked plan', function () {
    $hiddenWithoutPlan = FailureModeAnalysis::factory()->hidden()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'failure_finding_plan_id' => null,
    ]);
    $operational = FailureModeAnalysis::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'consequence_category' => FailureConsequenceCategory::Operational->value,
    ]);

    Livewire::test(FailureModeAnalysisRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->assertActionVisible(TestAction::make('createFailureFindingTask')->table($hiddenWithoutPlan))
        ->assertActionHidden(TestAction::make('createFailureFindingTask')->table($operational));
});

it('creates and activates a failure-finding maintenance plan and links it back to the analysis', function () {
    $analysis = FailureModeAnalysis::factory()->hidden()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'failure_mode' => FailureMode::Instrumentation->value,
        'failure_finding_plan_id' => null,
    ]);

    Livewire::test(FailureModeAnalysisRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('createFailureFindingTask')->table($analysis), data: [
            'name' => 'Inspección de instrumentación',
            'trigger_source' => MaintenanceTriggerSource::Calendar->value,
            'time_frequency' => 'monthly',
        ])
        ->assertHasNoActionErrors();

    $analysis->refresh();

    expect($analysis->failure_finding_plan_id)->not->toBeNull()
        ->and($analysis->needsFailureFindingTask())->toBeFalse();

    $plan = MaintenancePlan::find($analysis->failure_finding_plan_id);

    expect($plan->is_failure_finding)->toBeTrue()
        ->and($plan->is_active)->toBeTrue()
        ->and($plan->equipment_id)->toBe($this->equipment->id);
});
