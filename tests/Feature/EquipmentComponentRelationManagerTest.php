<?php

use App\Filament\Resources\Equipment\Pages\EditEquipment;
use App\Filament\Resources\Equipment\RelationManagers\ComponentsRelationManager;
use App\Models\Equipment;
use App\Models\EquipmentComponent;
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
