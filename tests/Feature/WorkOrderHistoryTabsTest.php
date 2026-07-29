<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// La lista mezclaba Abiertas, Cerradas y Canceladas en una sola tabla, sin forma
// de distinguir el trabajo pendiente del ya resuelto. «Abiertas» (por defecto) y
// «Histórico» las separan sin crear un recurso ni una tabla nuevos.
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

function historyTabsWorkOrder(Tenant $tenant, Equipment $equipment, User $admin, string $title): WorkOrder
{
    return app(WorkOrderService::class)->create([
        'tenant_id' => $tenant->id,
        'equipment_id' => $equipment->id,
        'work_order_type' => 'corrective',
        'priority' => 'p3_medium',
        'title' => $title,
        'description' => 'desc',
    ], $admin);
}

it('la pestaña Abiertas (por defecto) solo muestra las OT activas', function () {
    $open = historyTabsWorkOrder($this->tenant, $this->equipment, $this->admin, 'Abierta');
    $closed = historyTabsWorkOrder($this->tenant, $this->equipment, $this->admin, 'Cerrada');
    app(WorkOrderService::class)->transition($closed, WorkOrderStatus::Closed, $this->admin);

    Livewire::test(ListWorkOrders::class)
        ->assertSet('activeTab', 'abiertas')
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$closed]);
});

it('la pestaña Histórico muestra las OT cerradas y canceladas, no las abiertas', function () {
    $open = historyTabsWorkOrder($this->tenant, $this->equipment, $this->admin, 'Abierta');
    $closed = historyTabsWorkOrder($this->tenant, $this->equipment, $this->admin, 'Cerrada');
    $cancelled = historyTabsWorkOrder($this->tenant, $this->equipment, $this->admin, 'Cancelada');
    app(WorkOrderService::class)->transition($closed, WorkOrderStatus::Closed, $this->admin);
    app(WorkOrderService::class)->transition($cancelled, WorkOrderStatus::Cancelled, $this->admin);

    Livewire::test(ListWorkOrders::class)
        ->set('activeTab', 'historico')
        ->assertCanSeeTableRecords([$closed, $cancelled])
        ->assertCanNotSeeTableRecords([$open]);
});
