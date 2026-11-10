<?php

use App\Domain\Analytics\Services\MonthlyProductionComparison;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Executive\MonthlyProductionWidget;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
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

    expect((new Dashboard)->getWidgets())->toContain(MonthlyProductionWidget::class);

    Livewire::test(MonthlyProductionWidget::class, ['pageFilters' => ['plant_id' => $this->plant->id, 'year' => 2026, 'month' => 11]])
        ->assertOk()
        ->assertSee('Producción por mes · 2026')
        ->assertSee('Toneladas 2025')
        ->assertSee('4.670,0')
        ->assertSee('mejor mes')
        ->assertSee('▼ 6,6 %', false);

    // Un año sin cierres dice que no hay datos, sin ejes vacíos.
    Livewire::test(MonthlyProductionWidget::class, ['pageFilters' => ['plant_id' => $this->plant->id, 'year' => 2024, 'month' => 1]])
        ->assertSee('Todavía no hay meses cerrados');
});
