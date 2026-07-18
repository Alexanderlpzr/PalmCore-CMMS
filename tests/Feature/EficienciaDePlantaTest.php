<?php

use App\Filament\Pages\EficienciaDePlanta;
use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
use App\Filament\Widgets\Executive\PlantMonthlyEfficiencyHistoryWidget;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
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

it('renders with no plants at all', function (): void {
    Livewire::test(EficienciaDePlanta::class)->assertOk();
});

it('shows the current month efficiency for the selected plant', function (): void {
    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductionCalendarDay::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $plant->id,
        'calendar_date' => now()->startOfMonth(),
        'programmed_hours' => 22,
    ]);

    Livewire::test(PlantEfficiencyStatsWidget::class, ['pageFilters' => ['plant_id' => $plant->id]])
        ->assertOk()
        ->assertSee('100%');
});

it('shows the closed months history for the selected plant', function (): void {
    $plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    PlantMonthlyKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $plant->id,
        'year' => 2026,
        'month' => 5,
        'programmed_hours' => 452,
        'effective_hours' => 413.4,
    ]);

    Livewire::test(PlantMonthlyEfficiencyHistoryWidget::class, ['pageFilters' => ['plant_id' => $plant->id]])
        ->assertOk();
});

it('registers every plant efficiency widget on the page', function (): void {
    $widgets = (new EficienciaDePlanta)->getWidgets();

    expect($widgets)->toContain(
        PlantEfficiencyStatsWidget::class,
        PlantMonthlyEfficiencyHistoryWidget::class,
    );
});
