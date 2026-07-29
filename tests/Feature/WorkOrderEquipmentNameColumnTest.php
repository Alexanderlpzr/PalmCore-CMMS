<?php

use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'code' => 'A01REC.02.01',
        'name' => 'Unidad hidráulica tolva recepción',
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('la tabla de OT muestra el nombre del equipo con su código debajo', function () {
    $workOrder = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    Livewire::test(ListWorkOrders::class)
        ->assertCanSeeTableRecords([$workOrder])
        ->assertSee('Unidad hidráulica tolva recepción')
        ->assertSee('A01REC.02.01');
});

it('se puede buscar una OT por el nombre del equipo', function () {
    $buscada = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $otra = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $this->plant->id,
            'code' => 'PAL-99',
            'name' => 'Prensa de palmistería',
        ])->id,
    ]);

    Livewire::test(ListWorkOrders::class)
        ->searchTable('tolva recepción')
        ->assertCanSeeTableRecords([$buscada])
        ->assertCanNotSeeTableRecords([$otra]);
});

it('se puede seguir buscando una OT por el código del equipo', function () {
    $buscada = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
    ]);

    $otra = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'plant_id' => $this->plant->id,
            'code' => 'PAL-99',
            'name' => 'Prensa de palmistería',
        ])->id,
    ]);

    Livewire::test(ListWorkOrders::class)
        ->searchTable('A01REC.02.01')
        ->assertCanSeeTableRecords([$buscada])
        ->assertCanNotSeeTableRecords([$otra]);
});
