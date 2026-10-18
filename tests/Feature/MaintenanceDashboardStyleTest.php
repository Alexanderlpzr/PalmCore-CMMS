<?php

use App\Filament\Widgets\Analytics\DowntimeByReasonWidget;
use App\Filament\Widgets\Budget\BudgetStatsWidget;
use App\Filament\Widgets\Executive\PlantEfficiencyStatsWidget;
use App\Filament\Widgets\Executive\PlantEnergyStatsWidget;
use App\Filament\Widgets\Reliability\MaintenanceComplianceWidget;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Los tableros de mantenimiento con el estilo de Talento Humano: la cifra principal en la
 * tarjeta con degradado, las demás como tarjetas con su barra, y las gráficas sin datos
 * diciendo que no hay datos en vez de pintar ejes vacíos.
 */

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $user = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $user->tenants()->attach($this->tenant->id, ['joined_at' => now()]);

    setPermissionsTeamId($this->tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant($this->tenant);

    $this->filtros = ['plant_id' => $this->plant->id, 'preset' => 'month', 'year' => 2026, 'month' => 10];
});

it('puts plant availability in the big card of the dashboard', function (): void {
    Livewire::test(PlantEfficiencyStatsWidget::class, ['heroMetric' => 'availability', 'pageFilters' => $this->filtros])
        ->assertOk()
        ->assertSee('Disponibilidad de la planta')
        ->assertSee('MTBF · tiempo entre fallas')
        // La eficiencia y la productividad quedan como tarjetas pequeñas.
        ->assertSee('Eficiencia')
        ->assertSee('Productividad')
        ->assertDontSee('MTBF / MTTR Planta');
});

it('puts efficiency in the big card everywhere else', function (): void {
    Livewire::test(PlantEfficiencyStatsWidget::class, ['pageFilters' => $this->filtros])
        ->assertOk()
        ->assertSee('Eficiencia de la planta')
        ->assertSee('Disponibilidad');
});

it('shows compliance and open work orders as cards', function (): void {
    Livewire::test(MaintenanceComplianceWidget::class)
        ->assertOk()
        ->assertSee('Cumplimiento de Preventivo')
        ->assertSee('OT abiertas')
        ->assertSee('Nada pendiente');
});

it('shows what was spent and the energy per ton in big cards', function (): void {
    Livewire::test(BudgetStatsWidget::class, ['pageFilters' => $this->filtros])
        ->assertOk()
        ->assertSee('Gastado en mantenimiento')
        ->assertSee('Sin presupuesto asignado');

    Livewire::test(PlantEnergyStatsWidget::class, ['pageFilters' => $this->filtros])
        ->assertOk()
        ->assertSee('Energía por tonelada')
        ->assertSee('Falta el consumo o la fruta del período')
        // Sin dato no es cero: la tarjeta grande dice «—», no «0,00 kWh/t».
        ->assertDontSee('0,00 kWh/t')
        ->assertDontSee('Sin dato kWh');
});

it('says a chart has no data instead of drawing empty axes', function (): void {
    Livewire::test(DowntimeByReasonWidget::class, ['pageFilters' => $this->filtros])
        ->assertOk()
        ->assertSee('Todavía no hay datos en este período.');
});

it('gives every chart a maximum height, so none fills the whole screen', function (): void {
    $graficas = collect(File::allFiles(app_path('Filament/Widgets')))
        ->map(fn (SplFileInfo $file): string => 'App\\Filament\\Widgets\\'.str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $file->getRelativePathname()))
        ->filter(fn (string $class): bool => class_exists($class) && is_subclass_of($class, ChartWidget::class) && ! (new ReflectionClass($class))->isAbstract())
        ->values();

    $sinTope = $graficas
        ->filter(fn (string $class): bool => (new ReflectionClass($class))->getProperty('maxHeight')->getDefaultValue() === null)
        ->values()
        ->all();

    expect($graficas->count())->toBeGreaterThan(15)
        ->and($sinTope)->toBe([]);
});
