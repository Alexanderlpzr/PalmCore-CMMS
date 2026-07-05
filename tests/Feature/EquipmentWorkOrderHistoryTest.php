<?php

use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\Equipment\RelationManagers\WorkOrdersRelationManager;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
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

it('only lists work orders belonging to the viewed equipment', function () {
    $ownWorkOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $otherEquipment = Equipment::factory()->for($this->tenant)->create();
    $otherWorkOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $otherEquipment->id,
    ]);

    Livewire::test(WorkOrdersRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => ViewEquipment::class,
    ])
        ->assertCanSeeTableRecords([$ownWorkOrder])
        ->assertCanNotSeeTableRecords([$otherWorkOrder]);
});

it('does not allow creating work orders from the equipment history tab', function () {
    Livewire::test(WorkOrdersRelationManager::class, [
        'ownerRecord' => $this->equipment,
        'pageClass' => ViewEquipment::class,
    ])
        ->assertActionDoesNotExist(TestAction::make('create')->table());
});
