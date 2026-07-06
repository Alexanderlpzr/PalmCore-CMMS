<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Filament\Pages\MaintenanceCalendar;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
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

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'code' => 'EQ-01']);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('renders and places a scheduled work order on its planned day', function () {
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Planned->value,
        'title' => 'Cambio de correa',
        'planned_start_at' => now()->startOfMonth()->addDays(9)->setTime(8, 0),
    ]);

    Livewire::test(MaintenanceCalendar::class)
        ->assertOk()
        ->assertSee('Cambio de correa')
        ->assertSee('EQ-01');
});

it('moves between months and back to today', function () {
    $current = now()->startOfMonth();

    Livewire::test(MaintenanceCalendar::class)
        ->assertSet('month', $current->format('Y-m'))
        ->call('nextMonth')
        ->assertSet('month', CarbonImmutable::parse($current)->addMonth()->format('Y-m'))
        ->call('previousMonth')
        ->call('previousMonth')
        ->assertSet('month', CarbonImmutable::parse($current)->subMonth()->format('Y-m'))
        ->call('goToToday')
        ->assertSet('month', $current->format('Y-m'));
});

it('lists open work orders with no planned date as unscheduled', function () {
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Draft->value,
        'title' => 'Pendiente de programar',
        'planned_start_at' => null,
    ]);

    Livewire::test(MaintenanceCalendar::class)
        ->assertSee('Sin programar')
        ->assertSee('Pendiente de programar');
});

it('filters work orders by status', function () {
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::Planned->value, 'title' => 'OT planificada',
        'planned_start_at' => now()->startOfMonth()->addDays(5)->setTime(9, 0),
    ]);
    WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id, 'equipment_id' => $this->equipment->id,
        'status' => WorkOrderStatus::InProgress->value, 'title' => 'OT en ejecucion',
        'planned_start_at' => now()->startOfMonth()->addDays(6)->setTime(9, 0),
    ]);

    Livewire::test(MaintenanceCalendar::class)
        ->set('statusFilter', WorkOrderStatus::Planned->value)
        ->assertSee('OT planificada')
        ->assertDontSee('OT en ejecucion');
});

it('denies access to a user without the work-orders.view permission', function () {
    $outsider = User::factory()->create(['is_active' => true]);
    $outsider->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($outsider);

    expect(MaintenanceCalendar::canAccess())->toBeFalse();
});
