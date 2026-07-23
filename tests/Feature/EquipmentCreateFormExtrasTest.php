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

it('creates the equipment components inline from the create form', function () {
    Livewire::test(CreateEquipment::class)
        ->fillForm([
            'code' => 'EQ-COMP',
            'name' => 'Prensa con piezas',
            'plant_id' => $this->plant->id,
            'area_id' => $this->area->id,
            'components' => [
                ['name' => 'Unidad de potencia', 'criticality' => 'high', 'status' => 'active', 'useful_life_hours' => 5000],
                ['name' => 'Filtro', 'criticality' => 'medium', 'status' => 'active'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $equipment = Equipment::where('code', 'EQ-COMP')->firstOrFail();

    expect($equipment->components()->count())->toBe(2)
        ->and($equipment->components()->pluck('name')->all())->toContain('Unidad de potencia', 'Filtro');

    $power = $equipment->components()->where('name', 'Unidad de potencia')->first();

    expect($power->tenant_id)->toBe($this->tenant->id)
        ->and((int) $power->useful_life_hours)->toBe(5000);
});

it('creates equipment with no components when none are added', function () {
    Livewire::test(CreateEquipment::class)
        ->fillForm([
            'code' => 'EQ-NOCOMP',
            'name' => 'Equipo simple',
            'plant_id' => $this->plant->id,
            'area_id' => $this->area->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Equipment::where('code', 'EQ-NOCOMP')->firstOrFail()->components()->count())->toBe(0);
});
