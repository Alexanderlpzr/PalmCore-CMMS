<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Models\Equipment;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Programa `$hours` horas y `$tons` toneladas por día durante `$days` días del mes en curso. */
function programHours(Plant $plant, int $days, float $hours, float $tons = 0): void
{
    $date = now()->startOfMonth();

    for ($i = 0; $i < $days; $i++) {
        ProductionCalendarDay::create([
            'tenant_id' => $plant->tenant_id,
            'plant_id' => $plant->id,
            'calendar_date' => $date->copy()->addDays($i)->toDateString(),
            'programmed_hours' => $hours,
            'processed_tons' => $tons,
        ]);
    }
}

/**
 * Registra un paro *a continuación* del anterior de la planta.
 *
 * Los paros de una planta no se pisan: si dos coincidieran, sus horas se contarían
 * una sola vez (son la misma hora perdida). Encadenarlos es lo que hace que la
 * suma de este fixture sea también su unión, y que el número esperado por el test
 * signifique algo. El solape tiene sus propios tests.
 */
function stop(Plant $plant, StoppageCategory $category, float $hours, ?Equipment $equipment = null, bool $affectsProduction = true): void
{
    static $cursors = [];

    $startedAt = $cursors[$plant->id] ?? now()->startOfMonth()->addDays(2);
    $cursors[$plant->id] = $startedAt->copy()->addMinutes((int) round($hours * 60));

    app(DowntimeService::class)->register([
        'tenant_id' => $plant->tenant_id,
        'plant_id' => $plant->id,
        'equipment_id' => $equipment?->id,
        'stoppage_category' => $category,
        'affects_production' => $affectsProduction,
        'started_at' => $startedAt,
        'ended_at' => $cursors[$plant->id],
    ], test()->actor);
}

beforeEach(function (): void {
    $this->service = app(PlantKpiService::class);
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor = User::factory()->create();
});

// ── El número del cliente ────────────────────────────────────────────────────

it('reproduces the plant efficiency the client reports by hand', function (): void {
    // Junio 2026 real: 452 h programadas, 38,6 h perdidas → 413,4 h efectivas = 91,46 %.
    programHours($this->plant, 20, 22.6); // 452 h

    stop($this->plant, StoppageCategory::Mechanical, 20.0);
    stop($this->plant, StoppageCategory::RawMaterial, 18.6);

    $kpis = $this->service->calculate(
        $this->plant,
        now()->startOfMonth(),
        now()->endOfMonth(),
    );

    expect($kpis['programmed_hours'])->toBe(452.0)
        ->and($kpis['lost_hours'])->toBe(38.6)
        ->and($kpis['effective_hours'])->toBe(413.4)
        ->and($kpis['efficiency_percentage'])->toBe(91.46);
});

it('is honest when the planner never programmed the month', function (): void {
    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    // Sin denominador no hay eficiencia. Inventar 100% sería mentir.
    expect($kpis['programmed_hours'])->toBe(0.0)
        ->and($kpis['efficiency_percentage'])->toBeNull();
});

it('does not let a paro that cost no production hours dent the efficiency', function (): void {
    programHours($this->plant, 10, 20); // 200 h

    stop($this->plant, StoppageCategory::Mechanical, 8.0, affectsProduction: false);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['lost_hours'])->toBe(0.0)
        ->and($kpis['efficiency_percentage'])->toBe(100.0);
});

it('never reports negative effective hours', function (): void {
    programHours($this->plant, 1, 8);

    stop($this->plant, StoppageCategory::Mechanical, 24.0);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['effective_hours'])->toBe(0.0)
        ->and($kpis['efficiency_percentage'])->toBe(0.0);
});

// ── Lo que mantenimiento debe y lo que solo sufre ────────────────────────────

it('separates the hours maintenance owns from the ones it merely suffers', function (): void {
    programHours($this->plant, 20, 22.6);

    stop($this->plant, StoppageCategory::Mechanical, 12.0);
    stop($this->plant, StoppageCategory::Electrical, 8.0);
    stop($this->plant, StoppageCategory::RawMaterial, 30.0);   // no es de mantenimiento
    stop($this->plant, StoppageCategory::Utilities, 10.0);     // tampoco

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['lost_hours'])->toBe(60.0)
        ->and($kpis['maintenance_lost_hours'])->toBe(20.0);
});

it('computes plant MTBF and MTTR over the failures maintenance owns', function (): void {
    programHours($this->plant, 20, 22.6); // 452 h

    stop($this->plant, StoppageCategory::Mechanical, 12.0);
    stop($this->plant, StoppageCategory::Electrical, 8.0);
    // Ni el paro programado ni la falta de fruta son fallas.
    stop($this->plant, StoppageCategory::Planned, 6.0);
    stop($this->plant, StoppageCategory::RawMaterial, 12.6);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    // 452 − 38,6 = 413,4 h efectivas; 2 fallas de mantenimiento.
    expect($kpis['failure_count'])->toBe(2)
        ->and($kpis['effective_hours'])->toBe(413.4)
        ->and($kpis['mtbf_hours'])->toBe(206.7)
        // 20 h de mantenimiento correctivo entre 2 fallas.
        ->and($kpis['mttr_hours'])->toBe(10.0);
});

it('reports no MTBF when the plant had no failures', function (): void {
    programHours($this->plant, 10, 20);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['failure_count'])->toBe(0)
        ->and($kpis['mtbf_hours'])->toBeNull()
        ->and($kpis['mttr_hours'])->toBeNull();
});

// ── El mes cerrado ────────────────────────────────────────────────────────────

it('freezes the month with the efficiency derived by the database', function (): void {
    programHours($this->plant, 20, 22.6);
    stop($this->plant, StoppageCategory::Mechanical, 38.6);

    $snapshot = $this->service->snapshotMonth($this->plant, (int) now()->year, (int) now()->month);

    expect($snapshot->programmed_hours)->toBe(452.0)
        ->and($snapshot->effective_hours)->toBe(413.4)
        ->and($snapshot->efficiency_percentage)->toBe(91.46)
        ->and($snapshot->periodLabel())->toBe(now()->format('Y-m'));
});

it('corrects a closed month instead of duplicating it when a paro is entered late', function (): void {
    programHours($this->plant, 20, 22.6);

    $this->service->snapshotMonth($this->plant, (int) now()->year, (int) now()->month);

    // El supervisor registra tarde un paro de 38,6 h.
    stop($this->plant, StoppageCategory::Mechanical, 38.6);

    $corrected = $this->service->snapshotMonth($this->plant, (int) now()->year, (int) now()->month);

    expect(PlantMonthlyKpi::withoutGlobalScopes()->count())->toBe(1)
        ->and($corrected->efficiency_percentage)->toBe(91.46);
});

// ── Productividad, eficiencia y disponibilidad ────────────────────────────────

/**
 * Las cifras de referencia de la planta, montadas como paros reales.
 *
 *   HP 452 h · HASEO 8 h · HMTTO 14 h · HOPER 10 h · HPREN 420 h · FP 6.000 t
 */
function referenceMonth(Plant $plant): void
{
    programHours($plant, 20, 22.6, 300); // 452 h y 6.000 t

    stop($plant, StoppageCategory::Planned, 8.0);      // HASEO
    stop($plant, StoppageCategory::Mechanical, 14.0);  // HMTTO
    stop($plant, StoppageCategory::RawMaterial, 10.0); // HOPER
}

it('reproduces the three indicators the plant computes by hand', function (): void {
    referenceMonth($this->plant);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['programmed_hours'])->toBe(452.0)      // HP
        ->and($kpis['cleaning_hours'])->toBe(8.0)       // HASEO
        ->and($kpis['effective_hours'])->toBe(420.0)    // HPREN
        ->and($kpis['processed_tons'])->toBe(6000.0)    // FP
        // 420 / (452 − 8)
        ->and($kpis['efficiency_percentage'])->toBe(94.59)
        // 6000 / (452 − 8)
        ->and($kpis['productivity_tons_per_hour'])->toBe(13.51)
        // (452 − 8 − 14) / 452
        ->and($kpis['availability_percentage'])->toBe(95.13);
});

it('breaks the lost hours down into the plant own three buckets', function (): void {
    referenceMonth($this->plant);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    // HASEO + HMTTO + HOPER cierra contra el total, y HP contra el total + HPREN.
    expect($kpis['cleaning_hours'])->toBe(8.0)
        ->and($kpis['maintenance_lost_hours'] - $kpis['cleaning_hours'])->toBe(14.0)
        ->and($kpis['other_lost_hours'])->toBe(10.0)
        ->and($kpis['lost_hours'])->toBe(32.0)
        ->and($kpis['effective_hours'] + $kpis['lost_hours'])->toBe($kpis['programmed_hours']);
});

it('does not charge cleaning hours against the efficiency', function (): void {
    // Dos meses idénticos salvo que el segundo hizo preventivo: el aseo no puede
    // castigar al mes que sí lo hizo, que es lo que hacía la fórmula anterior.
    programHours($this->plant, 10, 20); // 200 h
    stop($this->plant, StoppageCategory::Planned, 20.0);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    // 180 h prensadas sobre 180 h prensables — no 180/200 = 90 %.
    expect($kpis['effective_hours'])->toBe(180.0)
        ->and($kpis['efficiency_percentage'])->toBe(100.0)
        // La disponibilidad sí lo siente: la planta estuvo parada esas horas.
        ->and($kpis['availability_percentage'])->toBe(90.0);
});

it('says nothing rather than zero when no fruit was recorded', function (): void {
    // Cero toneladas por hora es un mes catastrófico; un mes sin capturar no lo es.
    programHours($this->plant, 10, 20); // 200 h, sin toneladas

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['processed_tons'])->toBe(0.0)
        ->and($kpis['productivity_tons_per_hour'])->toBe(0.0)
        ->and($kpis['efficiency_percentage'])->toBe(100.0);
});

it('has no indicator at all when cleaning ate every paid hour', function (): void {
    programHours($this->plant, 1, 8, 100);
    stop($this->plant, StoppageCategory::Planned, 8.0);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    // Sin horas prensables no hay denominador. Cero o cien serían inventados.
    expect($kpis['efficiency_percentage'])->toBeNull()
        ->and($kpis['productivity_tons_per_hour'])->toBeNull()
        // La disponibilidad sí existe: se mide sobre las horas pagadas.
        ->and($kpis['availability_percentage'])->toBe(0.0);
});

it('freezes the three indicators when the month is closed', function (): void {
    referenceMonth($this->plant);

    $snapshot = $this->service->snapshotMonth($this->plant, (int) now()->year, (int) now()->month);

    expect($snapshot->cleaning_hours)->toBe(8.0)
        ->and($snapshot->processed_tons)->toBe(6000.0)
        ->and($snapshot->efficiency_percentage)->toBe(94.59)
        ->and($snapshot->productivity_tons_per_hour)->toBe(13.51)
        ->and($snapshot->availability_percentage)->toBe(95.13)
        ->and($snapshot->unplannedMaintenanceHours())->toBe(14.0);
});

it('keeps a hand-corrected tonnage when the month is recalculated', function (): void {
    referenceMonth($this->plant);

    $snapshot = $this->service->snapshotMonth($this->plant, (int) now()->year, (int) now()->month);

    // Báscula y laboratorio no coincidieron; el ingeniero corrige el total.
    $snapshot->update(['processed_tons' => 6120, 'processed_tons_is_manual' => true]);

    // Entra un paro atrasado y el mes se recalcula entero.
    stop($this->plant, StoppageCategory::Electrical, 4.0);
    $recalculated = $this->service->snapshotMonth($this->plant, (int) now()->year, (int) now()->month);

    expect($recalculated->processed_tons)->toBe(6120.0)
        // Las horas sí se recalculan: solo la tonelada estaba protegida.
        ->and($recalculated->lost_hours)->toBe(36.0);
});

// ── Multi-tenant ──────────────────────────────────────────────────────────────

it('never mixes another plant hours into this one', function (): void {
    programHours($this->plant, 10, 20); // 200 h

    $other = Tenant::factory()->create();
    $otherPlant = Plant::factory()->create(['tenant_id' => $other->id]);
    programHours($otherPlant, 10, 20);
    stop($otherPlant, StoppageCategory::Mechanical, 50.0);

    $kpis = $this->service->calculate($this->plant, now()->startOfMonth(), now()->endOfMonth());

    expect($kpis['programmed_hours'])->toBe(200.0)
        ->and($kpis['lost_hours'])->toBe(0.0)
        ->and($kpis['efficiency_percentage'])->toBe(100.0);
});
