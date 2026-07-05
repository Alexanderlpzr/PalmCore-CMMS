<?php

use App\Filament\Widgets\Analytics\EquipmentReliabilityTrendWidget;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->tenant = Tenant::factory()->create();
    app(TenantRolesSeeder::class)->run($this->tenant);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    $this->admin->assignRole('administrador-general');

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('mounts scoped to the given equipment and defaults to this month', function () {
    Livewire::test(EquipmentReliabilityTrendWidget::class, ['record' => $this->equipment])
        ->assertSet('filters.preset', 'month')
        ->assertOk();
});

it('only plots failures belonging to the given equipment', function () {
    $otherEquipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $this->equipment->id,
        'was_planned' => false,
        'duration_minutes' => 60,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);
    EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $otherEquipment->id,
        'was_planned' => false,
        'duration_minutes' => 600,
        'started_at' => now()->startOfMonth()->addDay(),
    ]);

    $component = Livewire::test(EquipmentReliabilityTrendWidget::class, ['record' => $this->equipment]);

    $getData = new ReflectionMethod($component->instance(), 'getData');
    $getData->setAccessible(true);
    $data = $getData->invoke($component->instance());

    expect($data['datasets'][1]['label'])->toBe('MTTR (h)')
        ->and(collect($data['datasets'][1]['data'])->last())->toBe(1.0);
});
