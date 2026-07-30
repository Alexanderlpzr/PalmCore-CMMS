<?php

use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\CreateWorkOrder;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('la fecha planificada se guarda al crear la OT', function () {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'work_order_type' => WorkOrderType::Corrective->value,
            'priority' => 'p3_medium',
            'title' => 'OT planificada',
            'description' => 'Trabajo programado',
            'planned_start_at' => '2026-08-15',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WorkOrder::where('title', 'OT planificada')->firstOrFail()->planned_start_at->format('Y-m-d'))
        ->toBe('2026-08-15');
});

it('al cerrar se registra la fecha ejecutada', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'work_order_type' => WorkOrderType::Corrective->value,
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->callAction(TestAction::make('close'), data: [
            'work_performed' => 'Se cambió el rodamiento',
            'failure_mode' => FailureMode::Bearing->value,
            'actual_end_at' => '2026-08-10',
        ]);

    $workOrder->refresh();

    expect($workOrder->status)->toBe(WorkOrderStatus::Closed)
        ->and($workOrder->actual_end_at->format('Y-m-d'))->toBe('2026-08-10');
});

it('el modal de cierre ya no pide la causa raíz', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'work_order_type' => WorkOrderType::Corrective->value,
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->mountAction(TestAction::make('close'))
        ->assertDontSee('Causa raíz');
});

it('con modo «Otro» la causa es obligatoria', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'work_order_type' => WorkOrderType::Corrective->value,
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->callAction(TestAction::make('close'), data: [
            'work_performed' => 'Se reemplazó la platina',
            'failure_mode' => FailureMode::Other->value,
        ])
        ->assertHasActionErrors(['failure_cause' => 'required']);

    expect($workOrder->fresh()->status)->toBe(WorkOrderStatus::Draft);
});

it('con un modo clasificado no se pide la causa: el modo ya la dice', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'work_order_type' => WorkOrderType::Corrective->value,
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->callAction(TestAction::make('close'), data: [
            'work_performed' => 'Se cambió el rodamiento',
            'failure_mode' => FailureMode::Bearing->value,
        ])
        ->assertHasNoActionErrors();

    expect($workOrder->fresh()->status)->toBe(WorkOrderStatus::Closed);
});

it('con modo «Otro» la causa escrita se guarda en la OT', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'work_order_type' => WorkOrderType::Corrective->value,
    ]);

    Livewire::test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
        ->callAction(TestAction::make('close'), data: [
            'work_performed' => 'Se reemplazó la platina',
            'failure_mode' => FailureMode::Other->value,
            'failure_cause' => 'Golpe de un racimo atascado',
        ]);

    expect($workOrder->fresh()->failure_cause)->toBe('Golpe de un racimo atascado');
});
