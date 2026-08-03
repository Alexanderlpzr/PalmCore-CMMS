<?php

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ProductividadYEficiencia;
use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
use App\Filament\Widgets\Executive\PlantHoursBreakdownWidget;
use App\Filament\Widgets\Executive\PlantMonthlyEfficiencyHistoryWidget;
use App\Filament\Widgets\Executive\PlantMonthlyProductivityHistoryWidget;
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

    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** Un día del mes indicado, con sus horas y sus toneladas. */
function calendarDay(Plant $plant, string $date, float $hours, float $tons): void
{
    ProductionCalendarDay::factory()->create([
        'tenant_id' => $plant->tenant_id,
        'plant_id' => $plant->id,
        'calendar_date' => $date,
        'programmed_hours' => $hours,
        'processed_tons' => $tons,
    ]);
}

// ── La página ─────────────────────────────────────────────────────────────────

it('renders with no plants at all', function (): void {
    Plant::query()->delete();

    Livewire::test(ProductividadYEficiencia::class)->assertOk();
});

it('is reachable from the navigation menu', function (): void {
    expect(ProductividadYEficiencia::shouldRegisterNavigation())->toBeTrue()
        ->and(ProductividadYEficiencia::getNavigationLabel())->toBe('Productividad y Eficiencia');
});

it('gathers the three indicators, the hours behind them and both histories', function (): void {
    $widgets = (new ProductividadYEficiencia)->getWidgets();

    expect($widgets)->toBe([
        PlantEfficiencyStatsWidget::class,
        PlantHoursBreakdownWidget::class,
        PlantMonthlyEfficiencyHistoryWidget::class,
        PlantMonthlyProductivityHistoryWidget::class,
    ]);
});

it('opens on the current month, which is the period the plant thinks in', function (): void {
    Livewire::test(ProductividadYEficiencia::class)
        ->assertOk()
        ->assertSchemaStateSet([
            'preset' => 'month',
            'month' => now()->month,
            'year' => now()->year,
        ], 'filtersForm');
});

// ── El período manda sobre los indicadores ────────────────────────────────────

it('reads only the month the filter asks for', function (): void {
    // Este mes prensó; el anterior no. Si el filtro no mandara, se mezclarían.
    calendarDay($this->plant, now()->startOfMonth()->toDateString(), 20, 300);
    calendarDay($this->plant, now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 20, 100);

    $filters = [
        'plant_id' => $this->plant->id,
        'preset' => 'month',
        'year' => now()->year,
        'month' => now()->month,
    ];

    Livewire::test(PlantHoursBreakdownWidget::class, ['pageFilters' => $filters])
        ->assertOk()
        ->assertSee('300,0 t')
        ->assertDontSee('400,0 t');
});

it('adds up a range of months instead of one', function (): void {
    $start = now()->startOfYear();

    calendarDay($this->plant, $start->copy()->toDateString(), 20, 300);
    calendarDay($this->plant, $start->copy()->addMonthNoOverflow()->toDateString(), 20, 250);
    calendarDay($this->plant, $start->copy()->addMonthsNoOverflow(2)->toDateString(), 20, 500);

    // Enero–febrero: 550 t, dejando marzo fuera.
    Livewire::test(PlantHoursBreakdownWidget::class, ['pageFilters' => [
        'plant_id' => $this->plant->id,
        'preset' => 'range',
        'range_year' => (int) $start->year,
        'range_from_month' => 1,
        'range_to_month' => 2,
    ]])
        ->assertOk()
        ->assertSee('550,0 t');
});

it('covers the whole year when the year preset is chosen', function (): void {
    $start = now()->startOfYear();

    calendarDay($this->plant, $start->copy()->toDateString(), 20, 300);
    calendarDay($this->plant, $start->copy()->addMonthsNoOverflow(2)->toDateString(), 20, 500);

    Livewire::test(PlantHoursBreakdownWidget::class, ['pageFilters' => [
        'plant_id' => $this->plant->id,
        'preset' => 'year',
        'year' => (int) $start->year,
    ]])
        ->assertOk()
        ->assertSee('800,0 t');
});

// ── La incoherencia que había ─────────────────────────────────────────────────

it('no longer shows the current month while the filter says twelve', function (): void {
    // Antes, el preset «últimos 12 meses» caía en silencio al mes en curso y el
    // número en pantalla no era el que el filtro prometía.
    [$from, $to] = DashboardPeriod::snapshotWindow(['preset' => DashboardPeriod::DEFAULT_PRESET]);

    expect($from->toDateString())->toBe(now()->startOfMonth()->subMonths(11)->toDateString())
        ->and($to->toDateString())->toBe(now()->endOfMonth()->toDateString());
});

it('resolves a snapshot window for every preset, never null', function (array $filters): void {
    [$from, $to] = DashboardPeriod::snapshotWindow($filters);

    expect($from)->not->toBeNull()
        ->and($to)->not->toBeNull()
        ->and($from->lessThanOrEqualTo($to))->toBeTrue();
})->with([
    'sin filtros' => [[]],
    'mes' => [['preset' => 'month', 'year' => 2026, 'month' => 6]],
    'año' => [['preset' => 'year', 'year' => 2026]],
    'rango' => [['preset' => 'range', 'range_year' => 2026, 'range_from_month' => 3, 'range_to_month' => 8]],
    'rango invertido' => [['preset' => 'range', 'range_year' => 2026, 'range_from_month' => 8, 'range_to_month' => 3]],
]);

// ── El filtro compartido ──────────────────────────────────────────────────────

it('offers the month range on the dashboard too', function (): void {
    // El rango existía en DashboardPeriod y en ninguna pantalla.
    $presets = (new Dashboard)->periodFilterComponents()[0]->getOptions();

    expect($presets)->toHaveKey('range')
        ->and($presets)->toHaveKey('month')
        ->and($presets)->toHaveKey('year');
});

it('keeps the dashboard opening on the current month', function (): void {
    Livewire::test(Dashboard::class)
        ->assertOk()
        ->assertSchemaStateSet(['preset' => 'month'], 'filtersForm');
});

// ── Los históricos ────────────────────────────────────────────────────────────

it('charts efficiency and availability together, and productivity apart', function (): void {
    PlantMonthlyKpi::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026,
        'month' => 6,
    ]);

    $filters = ['pageFilters' => ['plant_id' => $this->plant->id]];

    Livewire::test(PlantMonthlyEfficiencyHistoryWidget::class, $filters)->assertOk();
    Livewire::test(PlantMonthlyProductivityHistoryWidget::class, $filters)->assertOk();
});
