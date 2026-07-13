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

/** Programa `$hours` horas por día durante `$days` días del mes en curso. */
function programHours(Plant $plant, int $days, float $hours): void
{
    $date = now()->startOfMonth();

    for ($i = 0; $i < $days; $i++) {
        ProductionCalendarDay::create([
            'tenant_id' => $plant->tenant_id,
            'plant_id' => $plant->id,
            'calendar_date' => $date->copy()->addDays($i)->toDateString(),
            'programmed_hours' => $hours,
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
