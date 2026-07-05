<?php

use App\Filament\Resources\Equipment\Pages\CreateEquipment;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentPhoto;
use App\Models\Manufacturer;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->area = Area::factory()->forPlant($this->plant)->create();

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('creates a manufacturer inline via the equipment form and attaches tenant_id', function () {
    Livewire::test(CreateEquipment::class)
        ->callAction(
            TestAction::make('createOption')->schemaComponent('manufacturer_id'),
            data: ['code' => 'MAN-1', 'name' => 'Fabricante X']
        );

    $manufacturer = Manufacturer::where('code', 'MAN-1')->first();

    expect($manufacturer)->not->toBeNull()
        ->and($manufacturer->tenant_id)->toBe($this->tenant->id);
});

it('uploads a primary photo on equipment creation', function () {
    $file = UploadedFile::fake()->image('foto.jpg', 200, 200);

    Livewire::test(CreateEquipment::class)
        ->fillForm([
            'code' => 'EQ-200',
            'name' => 'Compresor de prueba',
            'plant_id' => $this->plant->id,
            'area_id' => $this->area->id,
            'primary_photo_path' => $file,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $equipment = Equipment::where('code', 'EQ-200')->firstOrFail();
    $photo = EquipmentPhoto::where('equipment_id', $equipment->id)->first();

    expect($photo)->not->toBeNull()
        ->and($photo->is_primary)->toBeTrue();
});
