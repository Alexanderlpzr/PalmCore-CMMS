<?php

use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ViewWorkOrder;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

const FAKE_VERIFICATION_SIGNATURE_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

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

it('shows the pending-verification banner once the técnico completes and signs the OT', function () {
    $service = app(WorkOrderService::class);
    $tech = User::factory()->create(['is_active' => true]);

    $wo = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Pendiente de revisión',
        'description' => 'desc',
    ], $this->admin);
    $service->assignTechnician($wo, $tech, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $this->admin);
    $service->transition($wo, WorkOrderStatus::InProgress, $this->admin);
    $service->transition($wo, WorkOrderStatus::Completed, $this->admin);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->assertSee('En revisión');
});

it('hides the pending-verification banner once the supervisor verifies the OT', function () {
    $service = app(WorkOrderService::class);
    $tech = User::factory()->create(['is_active' => true]);

    $wo = $service->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => 'Ya verificada',
        'description' => 'desc',
    ], $this->admin);
    $service->assignTechnician($wo, $tech, TechnicianRole::Technician);
    $service->transition($wo, WorkOrderStatus::Planned, $this->admin);
    $service->transition($wo, WorkOrderStatus::InProgress, $this->admin);
    $service->transition($wo, WorkOrderStatus::Completed, $this->admin);
    $service->transition($wo, WorkOrderStatus::Verified, $this->admin);

    Livewire::test(ViewWorkOrder::class, ['record' => $wo->id])
        ->assertDontSee('En revisión');
});
