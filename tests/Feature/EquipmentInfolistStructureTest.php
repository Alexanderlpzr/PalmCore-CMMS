<?php

use App\Filament\Resources\Equipment\Pages\ViewEquipment;
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
    $this->area = Area::factory()->forPlant($this->plant)->create(['name' => 'Clarificación']);

    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'area_id' => $this->area->id,
        'code' => 'CLA-01',
        'name' => 'Clarificador estático',
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('Identificación reúne el equipo, su sección y su estado', function () {
    Livewire::test(ViewEquipment::class, ['record' => $this->equipment->getKey()])
        ->assertSee('Identificación')
        ->assertSee('CLA-01')
        ->assertSee('Clarificador estático')
        ->assertSee('Sección')
        ->assertSee('Clarificación')
        ->assertSee('Estado')
        ->assertSee('Criticidad');
});

it('ya no muestra las tarjetas de Clasificación, Ubicación ni Auditoría', function () {
    Livewire::test(ViewEquipment::class, ['record' => $this->equipment->getKey()])
        ->assertDontSee('Clasificación')
        ->assertDontSee('Notas de ubicación')
        ->assertDontSee('Auditoría')
        ->assertDontSee('Equipo padre')
        ->assertDontSee('Creado por')
        ->assertDontSee('Actualizado por');
});

it('conserva la ficha técnica, el ciclo de vida y los indicadores en español', function () {
    Livewire::test(ViewEquipment::class, ['record' => $this->equipment->getKey()])
        ->assertSee('Ficha técnica')
        ->assertSee('Ciclo de Vida')
        ->assertSee('Indicadores de Confiabilidad')
        ->assertDontSee('Reliability KPIs');
});

it('las notas solo aparecen cuando el equipo tiene notas', function () {
    Livewire::test(ViewEquipment::class, ['record' => $this->equipment->getKey()])
        ->assertDontSee('Notas');

    $conNotas = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'area_id' => $this->area->id,
        'code' => 'CLA-02',
        'notes' => 'Requiere purga semanal del fondo',
    ]);

    Livewire::test(ViewEquipment::class, ['record' => $conNotas->getKey()])
        ->assertSee('Requiere purga semanal del fondo');
});
