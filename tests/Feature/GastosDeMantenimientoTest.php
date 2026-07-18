<?php

use App\Filament\Pages\GastosDeMantenimiento;
use App\Filament\Widgets\Costs\BudgetVsSpentWidget;
use App\Filament\Widgets\Costs\CostBreakdownWidget;
use App\Filament\Widgets\Costs\MonthlyCostByTypeWidget;
use App\Filament\Widgets\Costs\MonthlyWorkOrderCostsWidget;
use App\Models\Equipment;
use App\Models\MaintenanceBudget;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
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

    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id]);
});

function costWo(array $overrides = []): WorkOrder
{
    return WorkOrder::factory()->create(array_merge([
        'tenant_id' => test()->tenant->id,
        'plant_id' => test()->plant->id,
        'equipment_id' => test()->equipment->id,
        'completed_at' => now(),
    ], $overrides));
}

it('renders with no data', function (): void {
    Livewire::test(GastosDeMantenimiento::class)->assertOk();
});

it('shows spend against budget for the selected month', function (): void {
    costWo(['actual_cost_labor' => 3_000_000, 'actual_cost_total' => 3_000_000]);
    MaintenanceBudget::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => (int) now()->year,
        'month' => (int) now()->month,
        'amount' => 10_000_000,
    ]);

    Livewire::test(BudgetVsSpentWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'year' => (int) now()->year, 'month' => (int) now()->month],
    ])
        ->assertOk()
        ->assertSee('3.000.000')
        ->assertSee('10.000.000');
});

it('breaks spend down into labor, parts and external', function (): void {
    costWo(['actual_cost_labor' => 100, 'actual_cost_parts' => 200, 'actual_cost_external' => 50, 'actual_cost_total' => 350]);

    Livewire::test(CostBreakdownWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'year' => (int) now()->year, 'month' => (int) now()->month],
    ])
        ->assertOk()
        ->assertSee('Mano de obra')
        ->assertSee('Repuestos')
        ->assertSee('Terceros');
});

it('lists the month work orders with their cost', function (): void {
    costWo(['title' => 'Cambio de rodamiento', 'actual_cost_total' => 500]);

    Livewire::test(MonthlyWorkOrderCostsWidget::class, [
        'pageFilters' => ['plant_id' => $this->plant->id, 'year' => (int) now()->year, 'month' => (int) now()->month],
    ])
        ->assertOk()
        ->assertSee('Cambio de rodamiento');
});

it('exports the month to excel', function (): void {
    costWo(['actual_cost_total' => 500]);

    Livewire::test(GastosDeMantenimiento::class)
        ->callAction('exportExcel')
        ->assertHasNoActionErrors()
        ->assertFileDownloaded();
});

it('registers every cost widget on the page', function (): void {
    $widgets = (new GastosDeMantenimiento)->getWidgets();

    expect($widgets)->toContain(
        BudgetVsSpentWidget::class,
        CostBreakdownWidget::class,
        MonthlyCostByTypeWidget::class,
        MonthlyWorkOrderCostsWidget::class,
    );
});
