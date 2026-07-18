<?php

use App\Filament\Pages\ResumenEjecutivo;
use App\Filament\Widgets\Executive\AreaHealthWidget;
use App\Filament\Widgets\Executive\AvailabilityTrendWidget;
use App\Filament\Widgets\Executive\CostByTypeWidget;
use App\Filament\Widgets\Executive\CostTrendWidget;
use App\Filament\Widgets\Executive\ExecutiveSummaryWidget;
use App\Filament\Widgets\Executive\TopCriticalEquipmentWidget;
use App\Models\Area;
use App\Models\Equipment;
use App\Models\EquipmentKpi;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->user);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);
});

it('renders with no data at all', function (): void {
    Livewire::test(ResumenEjecutivo::class)->assertOk();
});

it('shows the fleet summary stats', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 91.50,
        'is_stale' => false,
    ]);

    Livewire::test(ExecutiveSummaryWidget::class)
        ->assertOk()
        ->assertSee('91.5%');
});

it('shows area health with its name and availability', function (): void {
    $area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'area_id' => $area->id]);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'availability_percentage' => 88.00,
    ]);

    Livewire::test(AreaHealthWidget::class)
        ->assertOk()
        ->assertSee($area->name)
        ->assertSee('88.0%');
});

it('shows top critical equipment by name', function (): void {
    $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Prensa 1']);

    EquipmentKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
        'failure_count' => 4,
    ]);

    Livewire::test(TopCriticalEquipmentWidget::class)
        ->assertOk()
        ->assertSee('Prensa 1');
});

it('registers every executive widget on the page', function (): void {
    // Regression guard, same shape as the one that caught the analytics
    // widgets that were built but never wired into Dashboard::getWidgets().
    $widgets = (new ResumenEjecutivo)->getWidgets();

    expect($widgets)->toContain(
        ExecutiveSummaryWidget::class,
        AreaHealthWidget::class,
        TopCriticalEquipmentWidget::class,
        CostByTypeWidget::class,
        AvailabilityTrendWidget::class,
        CostTrendWidget::class,
    );
});
