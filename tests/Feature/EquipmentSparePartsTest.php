<?php

use App\Filament\Resources\Equipment\Pages\ViewEquipment;
use App\Filament\Resources\Equipment\RelationManagers\SparePartsRelationManager;
use App\Models\Equipment;
use App\Models\EquipmentSparePart;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

function sparePartsManager(Equipment $equipment): Testable
{
    return Livewire::test(SparePartsRelationManager::class, [
        'ownerRecord' => $equipment,
        'pageClass' => ViewEquipment::class,
    ]);
}

it('agrega un repuesto al equipo con solo el nombre', function () {
    sparePartsManager($this->equipment)
        ->callAction(TestAction::make('create')->table(), data: ['name' => 'Rodamiento 6205'])
        ->assertHasNoActionErrors();

    $sparePart = EquipmentSparePart::where('equipment_id', $this->equipment->id)->firstOrFail();

    expect($sparePart->name)->toBe('Rodamiento 6205')
        ->and($sparePart->tenant_id)->toBe($this->tenant->id)
        ->and($sparePart->part_number)->toBeNull();
});

it('el nombre del repuesto es obligatorio', function () {
    sparePartsManager($this->equipment)
        ->callAction(TestAction::make('create')->table(), data: ['name' => ''])
        ->assertHasActionErrors(['name' => 'required']);

    expect(EquipmentSparePart::where('equipment_id', $this->equipment->id)->count())->toBe(0);
});

it('guarda la referencia cuando se indica', function () {
    sparePartsManager($this->equipment)
        ->callAction(TestAction::make('create')->table(), data: [
            'name' => 'Sello mecánico',
            'part_number' => 'REF-7788',
        ])
        ->assertHasNoActionErrors();

    expect(EquipmentSparePart::where('equipment_id', $this->equipment->id)->first()->part_number)
        ->toBe('REF-7788');
});

it('lista los repuestos del equipo y no los de otro', function () {
    $propio = EquipmentSparePart::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'name' => 'Correa A-52',
    ]);

    $ajeno = EquipmentSparePart::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $this->plant->id,
        ])->id,
        'name' => 'Piñón de otro equipo',
    ]);

    sparePartsManager($this->equipment)
        ->assertCanSeeTableRecords([$propio])
        ->assertCanNotSeeTableRecords([$ajeno]);
});

it('se puede quitar un repuesto de la lista', function () {
    $sparePart = EquipmentSparePart::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    sparePartsManager($this->equipment)
        ->callAction(TestAction::make('delete')->table($sparePart));

    expect($sparePart->fresh()->trashed())->toBeTrue();
});
