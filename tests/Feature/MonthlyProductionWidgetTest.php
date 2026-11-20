<?php

use App\Domain\Analytics\Services\MonthlyProductionComparison;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Analytics\CostByEquipmentWidget;
use App\Filament\Widgets\Analytics\DowntimeByEquipmentWidget;
use App\Filament\Widgets\Analytics\DowntimeBySectionWidget;
use App\Filament\Widgets\Costs\MonthlyCostByTypeWidget;
use App\Filament\Widgets\Executive\DashboardSummaryWidget;
use App\Filament\Widgets\Executive\MonthlyProductionWidget;
use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\WidgetConfiguration;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * La producción de cada mes en el Dashboard: barras del año al lado del mismo mes del año
 * anterior, y la tabla con toneladas, variación, horas efectivas y toneladas por hora.
 *
 * Hoy es el 9 de noviembre de 2026.
 */

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00'));
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $cierre = fn (int $year, int $month, float $tons, float $hours = 400) => PlantMonthlyKpi::factory()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
        'year' => $year, 'month' => $month, 'processed_tons' => $tons, 'effective_hours' => $hours,
    ]);

    $cierre(2025, 9, 4000);
    $cierre(2025, 10, 5000);
    $cierre(2026, 9, 4400, 440);
    $cierre(2026, 10, 4670, 467);
    $cierre(2026, 11, 900, 100);
});

it('compares each month with the same month of the year before', function (): void {
    $comparison = app(MonthlyProductionComparison::class)->forYear($this->tenant->id, $this->plant->id, 2026);
    $rows = collect($comparison['rows'])->keyBy('month');

    expect($rows[9])->toMatchArray(['label' => 'Sep', 'tons' => 4400.0, 'previousTons' => 4000.0, 'change' => 10.0, 'tonsPerHour' => 10.0])
        ->and($rows[10]['change'])->toBe(-6.6)
        // Noviembre va en curso; diciembre no ha empezado: sin dato, no cero.
        ->and($rows[11]['tons'])->toBe(900.0)
        ->and($rows[11]['previousTons'])->toBeNull()
        ->and($rows[12]['tons'])->toBeNull()
        ->and($rows[1]['tons'])->toBeNull();

    // El total compara los meses con dato este año contra esos mismos del anterior.
    expect($comparison['total'])->toMatchArray(['tons' => 9970.0, 'previousTons' => 9000.0, 'months' => 3])
        ->and($comparison['total']['change'])->toBe(10.8)
        ->and($comparison['best'])->toBe(['label' => 'Oct', 'tons' => 4670.0]);
});

it('draws the bars and the table on the dashboard', function (): void {
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    // Es lo primero del Dashboard, debajo del resumen, en la pestaña que abre.
    expect((new Dashboard)->getWidgets()[1])->toBe(MonthlyProductionWidget::class)
        ->and(MonthlyProductionWidget::getSort())->toBeLessThan(PlantEfficiencyStatsWidget::getSort());

    Livewire::test(MonthlyProductionWidget::class, ['pageFilters' => ['plant_id' => $this->plant->id, 'year' => 2026, 'month' => 11]])
        ->assertOk()
        ->assertSee('Producción por mes · 2026')
        ->assertSee('Toneladas 2025')
        ->assertSee('4.670,0')
        ->assertSee('mejor mes')
        ->assertSee('Toque una barra para ver el detalle de ese mes.')
        ->assertSee('Ver la tabla de todos los meses')
        ->assertSee('▼ 6,6 %', false);

    // Un año sin cierres dice que no hay datos, sin ejes vacíos.
    Livewire::test(MonthlyProductionWidget::class, ['pageFilters' => ['plant_id' => $this->plant->id, 'year' => 2024, 'month' => 1]])
        ->assertSee('Todavía no hay meses cerrados');
});

it('paints the bar figures with the theme color, readable in dark mode', function (): void {
    // Un gris fijo (#475569) se perdía sobre el fondo oscuro (2026-11-19).
    foreach ([MonthlyProductionWidget::class, DowntimeBySectionWidget::class] as $widget) {
        $options = (string) (fn () => $this->getOptions())->call(new $widget);

        expect($options)->toContain('frondaChartLabelColor')
            ->not->toContain('#475569');
    }

    expect(file_get_contents(resource_path('js/filament-chart-js-plugins.js')))
        ->toContain('BarElement')
        ->toContain('window.frondaChartLabelColor');
});

it('sums the production of a period against the same months of the year before', function (): void {
    $service = app(MonthlyProductionComparison::class);

    expect($service->forWindow($this->tenant->id, $this->plant->id, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31')))
        ->toBe(['tons' => 4670.0, 'previousTons' => 5000.0, 'change' => -6.6, 'includesCurrentMonth' => false])
        // Septiembre a noviembre: noviembre no tiene año anterior, pero va en curso.
        ->and($service->forWindow($this->tenant->id, $this->plant->id, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-11-30')))
        ->toBe(['tons' => 9970.0, 'previousTons' => 9000.0, 'change' => 10.8, 'includesCurrentMonth' => true]);
});

it('shows a summary on top and only the charts of the chosen tab', function (): void {
    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $widgetsOf = function (string $section): array {
        $dashboard = new Dashboard;
        $dashboard->seccion = $section;

        return collect($dashboard->getWidgets())->map(fn ($widget): string => $widget instanceof WidgetConfiguration ? $widget->widget : $widget)->all();
    };

    expect($widgetsOf('produccion'))->toBe([DashboardSummaryWidget::class, MonthlyProductionWidget::class, PlantEfficiencyStatsWidget::class])
        ->and($widgetsOf('paros'))->toContain(DowntimeByEquipmentWidget::class)->not->toContain(MonthlyProductionWidget::class)
        ->and($widgetsOf('costos'))->toBe([DashboardSummaryWidget::class, CostByEquipmentWidget::class, MonthlyCostByTypeWidget::class])
        // Una pestaña inventada abre la de producción.
        ->and($widgetsOf('cualquiera'))->toBe($widgetsOf('produccion'));

    Livewire::test(DashboardSummaryWidget::class, ['seccion' => 'paros', 'pageFilters' => ['plant_id' => $this->plant->id, 'preset' => 'month', 'year' => 2026, 'month' => 10]])
        ->assertOk()
        ->assertSee('Producción')
        ->assertSee('4.670 t')
        ->assertSee('▼ 6,6 % contra 2025')
        ->assertSee('Disponibilidad')
        ->assertSee('Horas de paro')
        ->assertSee('Preventivos al día')
        ->assertSee('Gasto de mantenimiento')
        ->assertSeeHtml("\$parent.set('seccion', 'costos')");

    $this->actingAs($user->fresh())->get(Dashboard::getUrl(['ver' => 'paros'], tenant: $this->tenant))->assertOk()->assertSee('Horas de paro')->assertSee('Confiabilidad');
});
