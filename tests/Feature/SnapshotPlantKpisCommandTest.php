<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Assets\Services\DowntimeService;
use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Infrastructure\Audit\Jobs\WriteAuditLog;
use App\Jobs\SnapshotPlantKpisJob;
use App\Models\EnergyMeter;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\ProductionCalendarDay;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor = User::factory()->create();
});

/** Programa un día de proceso con sus horas y toneladas. */
function diaDeProceso(Plant $plant, string $date, float $hours, float $tons = 0): void
{
    ProductionCalendarDay::create([
        'tenant_id' => $plant->tenant_id,
        'plant_id' => $plant->id,
        'calendar_date' => $date,
        'programmed_hours' => $hours,
        'processed_tons' => $tons,
    ]);
}

/**
 * Registra un paro cerrado de planta.
 *
 * No se llama `paro()` porque ese nombre ya lo usa OverlappingStoppagesTest y
 * Pest carga todos los archivos en el mismo proceso: la segunda declaración es
 * un error fatal que tumba la suite entera, no un test en rojo.
 */
function paroDeCierre(Plant $plant, string $from, float $hours, StoppageCategory $category = StoppageCategory::Mechanical): void
{
    $startedAt = Carbon::parse($from);

    app(DowntimeService::class)->register([
        'tenant_id' => $plant->tenant_id,
        'plant_id' => $plant->id,
        'stoppage_category' => $category,
        'affects_production' => true,
        'started_at' => $startedAt,
        'ended_at' => $startedAt->copy()->addMinutes((int) round($hours * 60)),
    ], test()->actor);
}

function cierreGuardado(Plant $plant, int $year, int $month): ?PlantMonthlyKpi
{
    return PlantMonthlyKpi::withoutGlobalScopes()
        ->where('plant_id', $plant->id)
        ->where('year', $year)
        ->where('month', $month)
        ->first();
}

// ── El caso real: un mes congelado antes de que llegaran los datos ───────────

it('corrects a month frozen before its stoppages were loaded', function (): void {
    // Es lo que pasó en producción: diez meses se cerraron con cero paros y la
    // importación del histórico llegó después. Las gráficas de tendencia leen el
    // cierre, no recalculan, así que enseñaban una línea plana sobre datos que sí
    // estaban en la base.
    diaDeProceso($this->plant, '2026-03-02', 100.0, 1000.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);

    expect(cierreGuardado($this->plant, 2026, 3)->failure_count)->toBe(0);

    paroDeCierre($this->plant, '2026-03-05 08:00', 4.0);
    paroDeCierre($this->plant, '2026-03-06 08:00', 2.0);

    $this->artisan('plant-kpis:snapshot', ['--from' => '2026-03', '--to' => '2026-03'])
        ->assertSuccessful();

    $corregido = cierreGuardado($this->plant, 2026, 3);

    expect($corregido->failure_count)->toBe(2)
        ->and((float) $corregido->lost_hours)->toBe(6.0);
});

it('leaves manually corrected tons alone when recalculating', function (): void {
    // Báscula y laboratorio no coinciden al cierre. Si recalcular se llevara la
    // corrección, duraría hasta el siguiente recálculo — y ahora recalculamos a
    // diario, así que no duraría nada.
    diaDeProceso($this->plant, '2026-03-02', 100.0, 1000.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);

    cierreGuardado($this->plant, 2026, 3)->update([
        'processed_tons' => 1234.0,
        'processed_tons_is_manual' => true,
    ]);

    $this->artisan('plant-kpis:snapshot', ['--from' => '2026-03'])->assertSuccessful();

    expect((float) cierreGuardado($this->plant, 2026, 3)->processed_tons)->toBe(1234.0);
});

it('does not touch the energy that came from the historical sheet', function (): void {
    // Los meses de la hoja no tienen lecturas diarias detrás: recalcular sobre
    // ellas los pondría en cero.
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 2);

    cierreGuardado($this->plant, 2026, 2)->update([
        'kwh_grid' => 8002.0,
        'kwh_genset' => 46351.0,
        'kwh_turbine' => 71970.0,
        'energy_is_imported' => true,
    ]);

    $this->artisan('plant-kpis:snapshot', ['--from' => '2026-02', '--to' => '2026-02'])
        ->assertSuccessful();

    $kpi = cierreGuardado($this->plant, 2026, 2);

    expect((float) $kpi->kwh_grid)->toBe(8002.0)
        ->and((float) $kpi->kwh_turbine)->toBe(71970.0);
});

// ── Lo que la simulación enseña ──────────────────────────────────────────────

it('shows on a dry run the energy that recalculating would correct', function (): void {
    // La primera versión comparaba una lista fija de cifras y no incluía la
    // energía: septiembre corrigió sus kWh sin que la simulación lo anunciara.
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 8);

    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);
    $lecturas = app(EnergyMeterReadingService::class);
    $lecturas->record($turbina, 2_463_979, $this->actor, Carbon::parse('2026-07-31'));
    $lecturas->record($turbina, 2_527_433, $this->actor, Carbon::parse('2026-08-19'));

    Artisan::call('plant-kpis:snapshot', ['--from' => '2026-08', '--to' => '2026-08', '--dry-run' => true]);

    expect(Artisan::output())
        ->toContain('energía')
        ->toContain('kWh turbina — → 63454');
});

it('shows the indicators Postgres derives, not only the stored figures', function (): void {
    // La eficiencia es una columna generada: la simulación la lee de la fila
    // escrita y deshecha, en vez de repetir aquí la fórmula.
    diaDeProceso($this->plant, '2026-03-02', 100.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);
    paroDeCierre($this->plant, '2026-03-05 08:00', 10.0);

    Artisan::call('plant-kpis:snapshot', ['--from' => '2026-03', '--to' => '2026-03', '--dry-run' => true]);

    expect(Artisan::output())->toContain('eficiencia 100 → 90');
});

// ── La auditoría ─────────────────────────────────────────────────────────────

it('leaves no audit trail of a simulation', function (): void {
    // El cierre es auditable. Una simulación que escribe y deshace dejaría, sin
    // los eventos apagados, correcciones que nunca ocurrieron en el registro que
    // existe justamente para distinguirlas de las reales.
    diaDeProceso($this->plant, '2026-03-02', 100.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);
    paroDeCierre($this->plant, '2026-03-05 08:00', 4.0);

    Queue::fake();

    Artisan::call('plant-kpis:snapshot', ['--from' => '2026-03', '--to' => '2026-03', '--dry-run' => true]);
    app()->terminate();

    // Vio el cambio…
    expect(Artisan::output())->toContain('fallas 0 → 1');

    // …y no lo apuntó como hecho.
    Queue::assertNotPushed(
        WriteAuditLog::class,
        fn (WriteAuditLog $job): bool => $job->modelClass === PlantMonthlyKpi::class && $job->event === 'updated',
    );
});

it('audits only the months whose figures actually changed', function (): void {
    // El reloj se fija porque `calculated_at` se guarda al segundo: si la
    // preparación y el comando cayeran en el mismo segundo, ni el cierre viejo
    // —el que reescribía siempre— reescribiría febrero, y la prueba pasaría sin
    // probar nada.
    Carbon::setTestNow('2026-04-01 04:00');
    diaDeProceso($this->plant, '2026-02-02', 100.0);
    diaDeProceso($this->plant, '2026-03-02', 100.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 2);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);

    Carbon::setTestNow('2026-04-02 04:00');

    // Solo marzo recibe un paro tarde.
    paroDeCierre($this->plant, '2026-03-05 08:00', 4.0);

    Queue::fake();

    Artisan::call('plant-kpis:snapshot', ['--from' => '2026-02', '--to' => '2026-03']);
    app()->terminate();

    $auditados = Queue::pushed(
        WriteAuditLog::class,
        fn (WriteAuditLog $job): bool => $job->modelClass === PlantMonthlyKpi::class && $job->event === 'updated',
    );

    expect($auditados)->toHaveCount(1)
        ->and((int) $auditados->first()->newValues['month'])->toBe(3);

    Carbon::setTestNow();
});

it('does not rewrite a month whose figures did not change', function (): void {
    // El cierre corre a diario sobre dos meses. Si escribiera siempre, cada pasada
    // dejaría una «corrección» en la que solo se movió la hora del cálculo.
    Carbon::setTestNow('2026-04-01 04:00');
    diaDeProceso($this->plant, '2026-03-02', 100.0);
    $primero = app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);

    Carbon::setTestNow('2026-04-02 04:00');
    Queue::fake();

    $otraVez = app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);
    app()->terminate();

    expect($otraVez->calculated_at->equalTo($primero->calculated_at))->toBeTrue();

    Queue::assertNotPushed(
        WriteAuditLog::class,
        fn (WriteAuditLog $job): bool => $job->modelClass === PlantMonthlyKpi::class && $job->event === 'updated',
    );

    Carbon::setTestNow();
});

it('stamps the moment a month actually changed', function (): void {
    Carbon::setTestNow('2026-04-01 04:00');
    diaDeProceso($this->plant, '2026-03-02', 100.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);

    Carbon::setTestNow('2026-04-02 04:00');
    paroDeCierre($this->plant, '2026-03-05 08:00', 4.0);

    $corregido = app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);

    expect($corregido->calculated_at->toDateTimeString())->toBe('2026-04-02 04:00:00')
        ->and($corregido->failure_count)->toBe(1);

    Carbon::setTestNow();
});

// ── El rango ─────────────────────────────────────────────────────────────────

it('recalculates every stored month with --all', function (): void {
    foreach ([1, 2, 3] as $month) {
        app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, $month);
    }

    paroDeCierre($this->plant, '2026-02-10 08:00', 3.0);

    $this->artisan('plant-kpis:snapshot', ['--all' => true])->assertSuccessful();

    expect(cierreGuardado($this->plant, 2026, 2)->failure_count)->toBe(1)
        ->and(PlantMonthlyKpi::withoutGlobalScopes()->count())->toBe(3);
});

it('writes nothing on a dry run', function (): void {
    diaDeProceso($this->plant, '2026-03-02', 100.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);
    paroDeCierre($this->plant, '2026-03-05 08:00', 4.0);

    $this->artisan('plant-kpis:snapshot', ['--from' => '2026-03', '--dry-run' => true])
        ->assertSuccessful();

    expect(cierreGuardado($this->plant, 2026, 3)->failure_count)->toBe(0);
});

it('announces no change on a dry run over a month already up to date', function (): void {
    // Sobre treinta y tres meses del histórico, una simulación que anuncia
    // cambios inexistentes es la que hace dudar de si conviene ejecutar. Las
    // cifras llegan de columnas `decimal` como texto y de `calculate()` como
    // float, así que sin normalizarlas «100.00» y «100.0» se verían distintas.
    diaDeProceso($this->plant, '2026-03-02', 100.0, 1000.0);
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 3);

    Artisan::call('plant-kpis:snapshot', ['--from' => '2026-03', '--to' => '2026-03', '--dry-run' => true]);

    expect(Artisan::output())->toContain('ninguno')->not->toContain('→');
});

it('refuses a month that is not written YYYY-MM', function (): void {
    $this->artisan('plant-kpis:snapshot', ['--from' => 'marzo'])->assertFailed();
    $this->artisan('plant-kpis:snapshot', ['--from' => '2026-13'])->assertFailed();
});

it('refuses a range that runs backwards', function (): void {
    $this->artisan('plant-kpis:snapshot', ['--from' => '2026-09', '--to' => '2026-03'])
        ->assertFailed();
});

it('asks for a range instead of guessing one', function (): void {
    $this->artisan('plant-kpis:snapshot')->assertFailed();
});

// ── El cierre agendado ───────────────────────────────────────────────────────

it('keeps both the current month and the previous one up to date', function (): void {
    // Agosto quedó congelado con 291,6 horas programadas cuando fueron 418,6,
    // porque el cierre corría una sola vez el día 1 y el calendario se terminó de
    // llenar después. Cerrando los dos meses abiertos, eso se corrige solo.
    Carbon::setTestNow('2026-09-15 06:00');

    diaDeProceso($this->plant, '2026-08-05', 300.0);
    diaDeProceso($this->plant, '2026-09-05', 200.0);

    app(SnapshotPlantKpisJob::class)->handle(app(PlantKpiService::class));

    expect((float) cierreGuardado($this->plant, 2026, 8)->programmed_hours)->toBe(300.0)
        ->and((float) cierreGuardado($this->plant, 2026, 9)->programmed_hours)->toBe(200.0);

    // Llega tarde el resto del calendario de agosto y el cierre vuelve a mirarlo.
    diaDeProceso($this->plant, '2026-08-06', 118.6);

    app(SnapshotPlantKpisJob::class)->handle(app(PlantKpiService::class));

    expect((float) cierreGuardado($this->plant, 2026, 8)->programmed_hours)->toBe(418.6);

    Carbon::setTestNow();
});

it('still closes one specific month when told to', function (): void {
    Carbon::setTestNow('2026-09-15 06:00');

    diaDeProceso($this->plant, '2026-03-05', 150.0);

    (new SnapshotPlantKpisJob(2026, 3))->handle(app(PlantKpiService::class));

    expect((float) cierreGuardado($this->plant, 2026, 3)->programmed_hours)->toBe(150.0)
        ->and(cierreGuardado($this->plant, 2026, 9))->toBeNull();

    Carbon::setTestNow();
});
