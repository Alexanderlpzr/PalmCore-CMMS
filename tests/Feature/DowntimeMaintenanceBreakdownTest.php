<?php

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Analytics\Support\PlantaGeneral;
use App\Domain\Assets\Enums\PlantSection;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageReason;
use App\Filament\Pages\IndicadoresDeParos;
use App\Filament\Widgets\Analytics\DowntimeBySectionWidget;
use App\Filament\Widgets\Analytics\DowntimeMaintenanceBreakdownWidget;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/*
 * El gráfico que reemplazó al de «Tipo I».
 *
 * El de Tipo I repartía las horas entre Programada / Mantenimiento / Operativa, que dice
 * **quién paró la línea** y no qué se rompió: «Operativa: 260 h» no le dice a nadie qué
 * arreglar. Este abre solo lo que mantenimiento puede arreglar, por causa concreta.
 *
 * Lo que más se prueba aquí es de dónde sale el corte: de la **causa física**, no del
 * Tipo I escrito a mano. En la planilla de El Pajuil hay 141,6 h de falla mecánica
 * anotadas como «Operativa»; cortando por Tipo I desaparecerían del informe.
 */
beforeEach(function (): void {
    Cache::flush();
    Carbon::setTestNow('2026-09-22 10:00');

    $this->tenant = Tenant::factory()->create();
    $this->equipo = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);

    // Filament exige un usuario autenticado para fijar el tenant de un widget.
    $this->admin = User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    $this->admin->tenants()->attach($this->tenant->id, ['joined_at' => now()]);
    $this->actingAs($this->admin);
});

afterEach(fn () => Carbon::setTestNow());

function paroClasificado(StoppageReason $causa, ReportedStoppageType $tipoI, StoppageCategory $categoria, float $horas): EquipmentDowntimeEvent
{
    // Un día distinto por paro: la base prohíbe dos paros solapados en el mismo equipo,
    // y con razón — un equipo no puede estar parado dos veces a la vez.
    static $dia = 1;
    $inicio = Carbon::parse('2026-09-01 06:00')->addDays($dia++);

    return EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => test()->tenant->id,
        'equipment_id' => test()->equipo->id,
        'started_at' => $inicio,
        'ended_at' => $inicio->copy()->addMinutes((int) ($horas * 60)),
        'duration_minutes' => (int) ($horas * 60),
        'reported_type' => $tipoI,
        'stoppage_reason' => $causa,
        'stoppage_category' => $categoria,
    ]);
}

/** @return array<string, float> */
function horasDeMantenimiento(): array
{
    $puntos = app(AnalyticsService::class)->downtimeMaintenanceByReason(
        test()->tenant->id,
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-09-30'),
    );

    return collect($puntos)->mapWithKeys(fn ($p): array => [$p->label => $p->value])->all();
}

it('cuenta la falla mecánica que la planilla anotó como Operativa', function (): void {
    // El caso que justifica todo el diseño: 141,6 h reales de El Pajuil están así.
    paroClasificado(StoppageReason::FallaMecanica, ReportedStoppageType::Operational, StoppageCategory::Mechanical, 5);
    paroClasificado(StoppageReason::FallaMecanica, ReportedStoppageType::Maintenance, StoppageCategory::Mechanical, 3);

    expect(horasDeMantenimiento())->toBe(['Falla mecánica' => 8.0]);
});

it('deja fuera el arranque y el apagado de planta', function (): void {
    // Los dos se registran como Tipo I «Programada», igual que el mantenimiento
    // programado, pero son maniobras de operación: meterlos aquí inflaría las horas de
    // mantenimiento con tiempo que no le toca.
    paroClasificado(StoppageReason::MantenimientoProgramado, ReportedStoppageType::Scheduled, StoppageCategory::Planned, 10);
    paroClasificado(StoppageReason::ArranqueDePlanta, ReportedStoppageType::Scheduled, StoppageCategory::Operational, 4);
    paroClasificado(StoppageReason::ApagadoDePlanta, ReportedStoppageType::Scheduled, StoppageCategory::Operational, 2);

    expect(horasDeMantenimiento())->toBe(['Mantenimiento programado' => 10.0]);
});

it('deja fuera atascamientos, falta de fruta y cortes de red', function (): void {
    paroClasificado(StoppageReason::Atascamiento, ReportedStoppageType::Operational, StoppageCategory::Process, 6);
    paroClasificado(StoppageReason::FaltaFrutaFresca, ReportedStoppageType::External, StoppageCategory::RawMaterial, 7);
    paroClasificado(StoppageReason::CorteEnergiaRed, ReportedStoppageType::External, StoppageCategory::Utilities, 3);
    paroClasificado(StoppageReason::FallaElectrica, ReportedStoppageType::Maintenance, StoppageCategory::Electrical, 2);

    expect(horasDeMantenimiento())->toBe(['Falla eléctrica' => 2.0]);
});

it('no comparte caché con el gráfico de todas las causas', function (): void {
    // Las dos consultas agrupan por la misma columna: con la clave de caché sin el
    // acotamiento, la segunda pantalla mostraría los números de la primera.
    paroClasificado(StoppageReason::FallaMecanica, ReportedStoppageType::Maintenance, StoppageCategory::Mechanical, 3);
    paroClasificado(StoppageReason::Atascamiento, ReportedStoppageType::Operational, StoppageCategory::Process, 6);

    $mantenimiento = horasDeMantenimiento();

    $todas = collect(app(AnalyticsService::class)->downtimeByReason(
        $this->tenant->id,
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-09-30'),
    ))->mapWithKeys(fn ($p): array => [$p->label => $p->value])->all();

    expect($mantenimiento)->toBe(['Falla mecánica' => 3.0])
        ->and($todas)->toBe(['Atascamiento' => 6.0, 'Falla mecánica' => 3.0]);
});

function graficaDeSecciones(): DowntimeBySectionWidget
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::setTenant(test()->tenant);

    return new DowntimeBySectionWidget;
}

/** @return array<string, mixed> */
function datosDeLaGrafica(DowntimeBySectionWidget $widget): array
{
    $metodo = new ReflectionMethod($widget, 'getData');
    $metodo->setAccessible(true);

    return $metodo->invoke($widget);
}

function paroEnSeccion(PlantSection $seccion, float $horas): EquipmentDowntimeEvent
{
    // Tres días de separación: estos paros duran decenas de horas y se pisarían.
    static $dia = 14;
    $inicio = Carbon::parse('2026-09-01 06:00')->addDays($dia);
    $dia += 3;

    return EquipmentDowntimeEvent::factory()->create([
        'tenant_id' => test()->tenant->id,
        'equipment_id' => test()->equipo->id,
        'started_at' => $inicio,
        'ended_at' => $inicio->copy()->addMinutes((int) ($horas * 60)),
        'duration_minutes' => (int) ($horas * 60),
        'section' => $seccion,
    ]);
}

// ── La pantalla ──────────────────────────────────────────────────────────────

it('la pantalla de paros ya no ofrece el gráfico de Tipo I', function (): void {
    $widgets = (new IndicadoresDeParos)->getWidgets();

    expect($widgets)->toContain(DowntimeMaintenanceBreakdownWidget::class)
        ->and(collect($widgets)->filter(fn (string $w): bool => str_contains($w, 'ReportedType'))->all())->toBe([])
        // El archivo también se fue: dejarlo huérfano invita a volver a cablearlo.
        ->and(file_exists(app_path('Filament/Widgets/Analytics/DowntimeByReportedTypeWidget.php')))->toBeFalse();
});

// ── Las definiciones ─────────────────────────────────────────────────────────

it('cada causa dice qué es, y las tres programadas se distinguen entre sí', function (): void {
    expect(StoppageReason::MantenimientoProgramado->description())->toContain('mantenimiento')
        ->and(StoppageReason::ApagadoDePlanta->description())->toContain('operación')
        ->and(StoppageReason::ArranqueDePlanta->description())->toContain('operación');

    // Ninguna se queda sin definir: la lista crece y el hueco pasaría inadvertido.
    foreach (StoppageReason::cases() as $causa) {
        expect(mb_strlen($causa->description()))->toBeGreaterThan(30);
    }
});

it('solo el mantenimiento programado cuenta como intervención de mantenimiento', function (): void {
    expect(StoppageReason::MantenimientoProgramado->category()->isMaintenanceResponsibility())->toBeTrue()
        ->and(StoppageReason::ApagadoDePlanta->category()->isMaintenanceResponsibility())->toBeFalse()
        ->and(StoppageReason::ArranqueDePlanta->category()->isMaintenanceResponsibility())->toBeFalse();
});

// ── Fuera el comodín de «Planta general» ─────────────────────────────────────

it('la gráfica por sección deja fuera «Planta general» pero dice cuántas horas aparta', function (): void {
    // En producción esa sección son 425,6 h contra 251,1 de Extracción, la siguiente: es
    // el cajón de los paros de toda la planta y aplasta a las secciones de verdad.
    paroEnSeccion(PlantSection::PlantaGeneral, 40);
    paroEnSeccion(PlantSection::Extraccion, 10);

    $widget = graficaDeSecciones();

    expect(datosDeLaGrafica($widget)['labels'])->toBe(['Extracción'])
        // Apartadas, no borradas: el subtítulo dice cuántas horas no se están viendo.
        ->and($widget->getDescription())->toContain('40,0 h')
        ->and($widget->getDescription())->toContain('Planta general');
});

it('no dice nada de «Planta general» cuando no hay paros suyos', function (): void {
    paroEnSeccion(PlantSection::Extraccion, 10);

    expect(graficaDeSecciones()->getDescription())->not->toContain('Planta general');
});

it('la gráfica por equipo deja fuera el equipo comodín y no pierde un puesto', function (): void {
    expect(PlantaGeneral::es('PLANTA GENERAL'))->toBeTrue()
        ->and(PlantaGeneral::es('Planta General'))->toBeTrue()
        ->and(PlantaGeneral::es('Prensa de Doble Tornillo'))->toBeFalse()
        ->and(PlantaGeneral::es(null))->toBeFalse();
});
