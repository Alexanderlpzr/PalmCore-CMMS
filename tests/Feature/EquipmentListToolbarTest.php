<?php

use App\Filament\Resources\Equipment\Pages\ListEquipment;
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

it('la lista de equipos ya no ofrece los exportes de confiabilidad ni de paradas', function () {
    Livewire::test(ListEquipment::class)
        ->assertActionDoesNotExist('export_reliability_excel')
        ->assertActionDoesNotExist('export_downtime_excel')
        ->assertActionExists('create');
});

it('la tabla sigue agrupada por Sección pero sin los selectores de agrupación', function () {
    $table = Livewire::test(ListEquipment::class)->instance()->getTable();

    expect($table->getGrouping()?->getId())->toBe('area.name')
        ->and($table->areGroupingSettingsHidden())->toBeTrue();
});
