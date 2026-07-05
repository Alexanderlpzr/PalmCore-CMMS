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
});

it('stores the unit cost of a component created from the equipment page', function () {
    $equipment = Equipment::factory()->for($this->tenant)->create();

    Livewire::test(ComponentsRelationManager::class, [
        'ownerRecord' => $equipment,
        'pageClass' => EditEquipment::class,
    ])
        ->callAction(TestAction::make('create')->table(), data: ['name' => 'Rodamiento', 'unit_cost' => 1500])
        ->assertHasNoActionErrors();

    $component = EquipmentComponent::where('name', 'Rodamiento')->first();

    expect((float) $component->unit_cost)->toBe(1500.0);
});

it('sums the investment across all components of an equipment', function () {
    $equipment = Equipment::factory()->for($this->tenant)->create(['replacement_cost' => 10000]);

    EquipmentComponent::factory()->forEquipment($equipment)->create(['unit_cost' => 3000]);
    EquipmentComponent::factory()->forEquipment($equipment)->create(['unit_cost' => 4000]);

    expect($equipment->componentsInvestmentTotal())->toBe(7000.0)
        ->and($equipment->componentsInvestmentRatio())->toBe(0.7);
});

it('returns no ratio when the equipment has no replacement cost registered', function () {
    $equipment = Equipment::factory()->for($this->tenant)->create(['replacement_cost' => null]);

    EquipmentComponent::factory()->forEquipment($equipment)->create(['unit_cost' => 3000]);

    expect($equipment->componentsInvestmentRatio())->toBeNull();
});
