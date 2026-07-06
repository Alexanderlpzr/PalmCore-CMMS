<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\CreateWorkOrder;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
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

    $this->technician = User::factory()->create(['is_active' => true]);
    $this->technician->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->technician->assignRole('tecnico');

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('assigns technicians chosen on the create form so the OT can be planned right away', function () {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'work_order_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => 'OT con técnico desde creación',
            'description' => 'Trabajo de prueba',
            'technician_ids' => [$this->technician->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $wo = WorkOrder::where('title', 'OT con técnico desde creación')->firstOrFail();

    expect($wo->technicians()->where('user_id', $this->technician->id)->exists())->toBeTrue();

    // The whole point: it can now transition straight to Planned (which requires
    // at least one technician) without a detour through the relation manager.
    app(WorkOrderService::class)->transition($wo, WorkOrderStatus::Planned, $this->admin);

    expect($wo->fresh()->status)->toBe(WorkOrderStatus::Planned);
});

it('creates a draft without technicians when none are chosen', function () {
    Livewire::test(CreateWorkOrder::class)
        ->fillForm([
            'equipment_id' => $this->equipment->id,
            'work_order_type' => 'corrective',
            'priority' => 'p3_medium',
            'title' => 'OT sin técnico',
            'description' => 'Trabajo de prueba',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $wo = WorkOrder::where('title', 'OT sin técnico')->firstOrFail();

    expect($wo->technicians()->count())->toBe(0)
        ->and($wo->status)->toBe(WorkOrderStatus::Draft);
});
