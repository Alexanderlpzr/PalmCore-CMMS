<?php

use App\Domain\Maintenance\Enums\WorkOrderTaskStatus;
use App\Filament\Pages\MaintenanceCalendar;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\RelationManagers\TasksRelationManager;
use App\Models\Alert;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistResult;
use App\Models\WorkOrderTask;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * El checklist del preventivo desde la web (Fase 1 de El Pajuil): se llena en la pestaña
 * «Checklist» de la OT, se omite con motivo, y un valor fuera de rango avisa. Y el
 * programa del día se imprime desde Órdenes de trabajo y el Calendario.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->user->assignRole('mantenimiento');

    $this->actingAs($this->user->fresh());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->ot = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->tarea = WorkOrderTask::factory()->for($this->ot)->create(['tenant_id' => $this->tenant->id, 'sort_order' => 1, 'title' => 'Inspección de la prensa']);
    $this->valvula = WorkOrderChecklistResult::factory()->create(['tenant_id' => $this->tenant->id, 'work_order_task_id' => $this->tarea->id, 'sort_order' => 1, 'label' => '¿Sin fuga de vapor?']);
    $this->vibracion = WorkOrderChecklistResult::factory()->numeric(2.0, 7.1, 'mm/s')->create(['tenant_id' => $this->tenant->id, 'work_order_task_id' => $this->tarea->id, 'sort_order' => 2, 'label' => 'Vibración']);
});

it('shows the checklist tab only on work orders that bring tasks', function (): void {
    $sinTareas = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(TasksRelationManager::canViewForRecord($this->ot, ViewWorkOrder::class))->toBeTrue()
        ->and(TasksRelationManager::canViewForRecord($sinTareas, ViewWorkOrder::class))->toBeFalse()
        ->and(TasksRelationManager::getBadge($this->ot, ViewWorkOrder::class))->toBe('0/1');
});

it('fills the measurements and marks the task done', function (): void {
    Livewire::test(TasksRelationManager::class, ['ownerRecord' => $this->ot, 'pageClass' => ViewWorkOrder::class])
        ->assertCanSeeTableRecords([$this->tarea])
        ->callAction(TestAction::make('llenar')->table($this->tarea), [
            'r_'.$this->valvula->id => '1',
            'r_'.$this->vibracion->id => 4.5,
            'marcar_hecha' => true,
        ])
        ->assertHasNoFormErrors();

    expect($this->tarea->refresh()->status)->toBe(WorkOrderTaskStatus::Done)
        ->and($this->valvula->refresh()->value_boolean)->toBeTrue()
        ->and((float) $this->vibracion->refresh()->value_numeric)->toBe(4.5)
        ->and($this->vibracion->is_out_of_range)->toBeFalse();
});

it('warns when a measurement is out of range', function (): void {
    Livewire::test(TasksRelationManager::class, ['ownerRecord' => $this->ot, 'pageClass' => ViewWorkOrder::class])
        ->callAction(TestAction::make('llenar')->table($this->tarea), [
            'r_'.$this->valvula->id => '0',
            'r_'.$this->vibracion->id => 9.3,
            'marcar_hecha' => true,
        ])
        ->assertNotified('Guardado, con mediciones fuera de rango');

    expect($this->vibracion->refresh()->is_out_of_range)->toBeTrue()
        ->and(Alert::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBeGreaterThan(0);
});

it('skips a task only with a reason', function (): void {
    Livewire::test(TasksRelationManager::class, ['ownerRecord' => $this->ot, 'pageClass' => ViewWorkOrder::class])
        ->mountAction(TestAction::make('omitir')->table($this->tarea))
        ->fillForm(['motivo' => ''])
        ->callMountedAction()
        ->assertHasFormErrors(['motivo' => 'required'])
        ->fillForm(['motivo' => 'Sin repuesto en almacén'])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($this->tarea->refresh()->status)->toBe(WorkOrderTaskStatus::Skipped)
        ->and($this->tarea->skipped_reason)->toBe('Sin repuesto en almacén');
});

it('prints the daily program from work orders and the calendar', function (): void {
    Livewire::test(ListWorkOrders::class)
        ->callAction('programaDelDia', ['dia' => '2026-10-19'])
        ->assertFileDownloaded();

    Livewire::test(MaintenanceCalendar::class)
        ->assertActionExists('programaDelDia');
});
