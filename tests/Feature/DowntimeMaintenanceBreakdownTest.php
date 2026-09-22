<?php

use App\Domain\Analytics\Services\AnalyticsService;
use App\Domain\Assets\Enums\ReportedStoppageType;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Enums\StoppageReason;
use App\Filament\Pages\IndicadoresDeParos;
use App\Filament\Widgets\Analytics\DowntimeMaintenanceBreakdownWidget;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeEvent;
use App\Models\Tenant;
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
