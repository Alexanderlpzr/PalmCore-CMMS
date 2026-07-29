<?php

use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Filament\Resources\Downtime\DowntimeEventResource;
use App\Filament\Resources\Maintenance\WorkOrder\Pages\ListWorkOrders;
use App\Models\Area;
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

    $this->palmisteria = Area::factory()->forPlant($this->plant)->create(['name' => 'Palmistería']);
    $this->recepcion = Area::factory()->forPlant($this->plant)->create(['name' => 'Recepción']);

    $this->prensa = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'area_id' => $this->palmisteria->id,
        'code' => 'PAL-01',
        'name' => 'Prensa',
    ]);
    $this->tolva = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'area_id' => $this->recepcion->id,
        'code' => 'REC-01',
        'name' => 'Tolva',
    ]);

    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('filtra las OT por sección', function () {
    $otPrensa = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->prensa->id,
    ]);
    $otTolva = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->tolva->id,
    ]);

    Livewire::test(ListWorkOrders::class)
        ->filterTable('ubicacion', ['area_id' => $this->palmisteria->id])
        ->assertCanSeeTableRecords([$otPrensa])
        ->assertCanNotSeeTableRecords([$otTolva]);
});

it('filtra las OT por equipo, que es la hoja de vida del equipo', function () {
    $otPrensa = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->prensa->id,
    ]);
    $otTolva = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->tolva->id,
    ]);

    Livewire::test(ListWorkOrders::class)
        ->filterTable('ubicacion', ['equipment_id' => $this->prensa->id])
        ->assertCanSeeTableRecords([$otPrensa])
        ->assertCanNotSeeTableRecords([$otTolva]);
});

it('el filtro por equipo se combina con la pestaña Histórico', function () {
    $cerrada = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->prensa->id,
        'status' => WorkOrderStatus::Closed->value,
    ]);
    $abierta = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->prensa->id,
        'status' => WorkOrderStatus::Draft->value,
    ]);
    $otraCerrada = WorkOrder::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->tolva->id,
        'status' => WorkOrderStatus::Closed->value,
    ]);

    Livewire::test(ListWorkOrders::class)
        ->set('activeTab', 'historico')
        ->filterTable('ubicacion', ['equipment_id' => $this->prensa->id])
        ->assertCanSeeTableRecords([$cerrada])
        ->assertCanNotSeeTableRecords([$abierta, $otraCerrada]);
});

it('el panel lateral llama Paradas de Planta a los paros', function () {
    expect(DowntimeEventResource::getNavigationLabel())->toBe('Paradas de Planta')
        ->and(DowntimeEventResource::getPluralModelLabel())->toBe('Paradas de Planta');
});
