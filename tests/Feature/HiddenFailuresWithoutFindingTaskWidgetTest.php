<?php

use App\Domain\Maintenance\Enums\FailureConsequenceCategory;
use App\Filament\Widgets\Analytics\HiddenFailuresWithoutFindingTaskWidget;
use App\Models\Equipment;
use App\Models\FailureModeAnalysis;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/**
 * El entregable operacional del alcance RCM pragmático: convierte el
 * catálogo de fallas ocultas en una lista de pendientes, no en datos
 * muertos. Solo debe listar lo que de verdad falta cerrar.
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
});

it('lists a hidden failure that has no failure-finding plan linked', function () {
    $equipment = Equipment::factory()->for($this->tenant)->create(['name' => 'Prensa Principal']);
    FailureModeAnalysis::factory()->hidden()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'failure_finding_plan_id' => null,
    ]);

    Livewire::test(HiddenFailuresWithoutFindingTaskWidget::class)
        ->assertSee('Prensa Principal');
});

it('does not list a hidden failure that already has a failure-finding plan', function () {
    $equipment = Equipment::factory()->for($this->tenant)->create(['name' => 'Caldera Sur']);
    $plan = MaintenancePlan::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'is_failure_finding' => true,
    ]);
    FailureModeAnalysis::factory()->hidden()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'failure_finding_plan_id' => $plan->id,
    ]);

    Livewire::test(HiddenFailuresWithoutFindingTaskWidget::class)
        ->assertDontSee('Caldera Sur');
});

it('does not list an analysis whose consequence is not hidden', function () {
    $equipment = Equipment::factory()->for($this->tenant)->create(['name' => 'Motor Norte']);
    FailureModeAnalysis::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'consequence_category' => FailureConsequenceCategory::Operational->value,
    ]);

    Livewire::test(HiddenFailuresWithoutFindingTaskWidget::class)
        ->assertDontSee('Motor Norte');
});
