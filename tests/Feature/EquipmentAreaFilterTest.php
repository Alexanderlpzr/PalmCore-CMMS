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

it('el filtro de Sección limita la lista de equipos a esa sección', function () {
    $palmisteria = Area::factory()->forPlant($this->plant)->create(['name' => 'Palmistería']);
    $recepcion = Area::factory()->forPlant($this->plant)->create(['name' => 'Recepción']);

    $equipoPalmisteria = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'area_id' => $palmisteria->id,
        'code' => 'PAL-01',
    ]);
    $equipoRecepcion = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'area_id' => $recepcion->id,
        'code' => 'REC-01',
    ]);

    Livewire::test(ListEquipment::class)
        ->filterTable('area_id', $palmisteria->id)
        ->assertCanSeeTableRecords([$equipoPalmisteria])
        ->assertCanNotSeeTableRecords([$equipoRecepcion]);
});
