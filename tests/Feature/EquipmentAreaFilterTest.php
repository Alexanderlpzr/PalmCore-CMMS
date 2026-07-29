<?php

use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

/**
 * @return array{0: Area, 1: Equipment, 2: Equipment}
 */
function seedTwoSections(Tenant $tenant, Plant $plant): array
{
    $palmisteria = Area::factory()->forPlant($plant)->create(['name' => 'Palmistería']);
    $recepcion = Area::factory()->forPlant($plant)->create(['name' => 'Recepción']);

    $equipoPalmisteria = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'area_id' => $palmisteria->id,
        'code' => 'PAL-01',
    ]);
    $equipoRecepcion = Equipment::factory()->create([
        'tenant_id' => $tenant->id,
        'plant_id' => $plant->id,
        'area_id' => $recepcion->id,
        'code' => 'REC-01',
    ]);

    return [$palmisteria, $equipoPalmisteria, $equipoRecepcion];
}

it('el filtro de Sección limita la lista de equipos a esa sección', function () {
    [$palmisteria, $equipoPalmisteria, $equipoRecepcion] = seedTwoSections($this->tenant, $this->plant);

    Livewire::test(ListEquipment::class)
        ->filterTable('area_id', $palmisteria->id)
        ->assertCanSeeTableRecords([$equipoPalmisteria])
        ->assertCanNotSeeTableRecords([$equipoRecepcion]);
});

it('el selector de Sección de la barra aplica el filtro sin pulsar Aplicar', function () {
    [$palmisteria, $equipoPalmisteria, $equipoRecepcion] = seedTwoSections($this->tenant, $this->plant);

    // Enlace exacto que usa el <select> del render hook de la barra.
    Livewire::test(ListEquipment::class)
        ->set('tableFilters.area_id.value', $palmisteria->id)
        ->assertCanSeeTableRecords([$equipoPalmisteria])
        ->assertCanNotSeeTableRecords([$equipoRecepcion]);
});

it('el selector de Sección se muestra en la barra con las secciones del tenant', function () {
    [$palmisteria] = seedTwoSections($this->tenant, $this->plant);

    Livewire::test(ListEquipment::class)
        ->assertSee('Todas las secciones')
        ->assertSeeHtml('wire:model.live="tableFilters.area_id.value"')
        ->assertSeeHtml('<option value="'.$palmisteria->id.'">Palmistería</option>');
});
