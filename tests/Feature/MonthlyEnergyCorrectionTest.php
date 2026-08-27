<?php

use App\Domain\Analytics\Services\MonthlyEnergyCorrectionService;
use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Exceptions\BusinessRuleException;
use App\Infrastructure\Audit\Jobs\WriteAuditLog;
use App\Models\EnergyMeter;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/**
 * Corregir a mano el total de un mes de la planilla.
 *
 * Existe porque las cifras se equivocan: la hoja original traía la turbina de agosto
 * inflada en 3.706 kWh por una fórmula que restaba la fila equivocada, y la producción del
 * mismo mes entró entera en kilogramos. Hasta ahora arreglar eso exigía entrar por consola.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->actingAs($this->user);

    $this->service = app(MonthlyEnergyCorrectionService::class);

    // Agosto de 2026 tal como lo dejó la importación.
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026, 'month' => 8,
        'processed_tons' => 3751.46,
        'kwh_grid' => 1277, 'kwh_genset' => 12363, 'kwh_turbine' => 67160,
        'energy_is_imported' => true,
        'calculated_at' => now(),
    ]);
});

function mesDeAgosto(object $t): PlantMonthlyKpi
{
    return PlantMonthlyKpi::withoutGlobalScopes()
        ->where('year', 2026)->where('month', 8)->first();
}

it('corrige la turbina y recalcula los tres derivados', function (): void {
    // El caso real: la hoja decía 67.160 y el acumulado del contador prueba 63.454.
    $this->service->apply($this->plant, 2026, 8, [
        'processed_tons' => 3751.46,
        'kwh_grid' => 1277,
        'kwh_genset' => 12363,
        'kwh_turbine' => 63454,
    ]);

    $mes = mesDeAgosto($this);

    expect($mes->kwh_turbine)->toBe(63454.0)
        // Los tres se mueven solos, sin que nadie los teclee.
        ->and($mes->kwh_total)->toBe(77094.0)
        ->and($mes->clean_energy_percentage)->toBe(82.31)
        ->and($mes->kwh_per_ton)->toBe(20.55);
});

it('deja en NULL el campo que se vacía, en vez de ponerlo en cero', function (): void {
    $this->service->apply($this->plant, 2026, 8, [
        'processed_tons' => 3751.46,
        'kwh_grid' => 1277,
        'kwh_genset' => 12363,
        'kwh_turbine' => null,
    ]);

    $mes = mesDeAgosto($this);

    // Cero afirmaría que la turbina no generó nada; vacío dice que no se sabe, y por eso
    // el porcentaje de energía limpia desaparece en vez de dar cero.
    expect($mes->kwh_turbine)->toBeNull()
        ->and($mes->clean_energy_percentage)->toBeNull()
        ->and($mes->kwh_total)->toBe(13640.0);
});

it('marca el mes para que el cierre no lo pise', function (): void {
    $this->service->apply($this->plant, 2026, 8, [
        'processed_tons' => 3751.46, 'kwh_grid' => 1277,
        'kwh_genset' => 12363, 'kwh_turbine' => 63454,
    ]);

    // El día 1 el cierre recalcula el mes que acaba de terminar. Sin las marcas, la
    // corrección duraría hasta esa madrugada.
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 8);

    $mes = mesDeAgosto($this);

    expect($mes->kwh_turbine)->toBe(63454.0)
        ->and($mes->processed_tons)->toBe(3751.46)
        ->and($mes->energy_is_imported)->toBeTrue()
        ->and($mes->processed_tons_is_manual)->toBeTrue();
});

it('rechaza un mes con las unidades cambiadas', function (): void {
    expect(fn () => $this->service->apply($this->plant, 2026, 8, [
        'processed_tons' => 3_751_460, // kilogramos otra vez
        'kwh_grid' => 1277, 'kwh_genset' => 12363, 'kwh_turbine' => 63454,
    ]))->toThrow(BusinessRuleException::class);
});

it('rechaza una cifra negativa', function (): void {
    expect(fn () => $this->service->apply($this->plant, 2026, 8, [
        'kwh_turbine' => -100,
    ]))->toThrow(BusinessRuleException::class);
});

// ── La vuelta atrás ──────────────────────────────────────────────────────────

it('devuelve el mes a lo que dicen los contadores', function (): void {
    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);
    $lecturas = app(EnergyMeterReadingService::class);
    $lecturas->record($turbina, 2_463_979, $this->user, Carbon::parse('2026-07-31'));
    $lecturas->record($turbina, 2_527_433, $this->user, Carbon::parse('2026-08-19'));

    // Alguien corrigió el mes a mano, y se arrepiente.
    $this->service->apply($this->plant, 2026, 8, ['kwh_turbine' => 999]);
    expect(mesDeAgosto($this)->kwh_turbine)->toBe(999.0);

    $this->service->recalculateFromReadings($this->plant, 2026, 8);

    $mes = mesDeAgosto($this);

    expect($mes->kwh_turbine)->toBe(63454.0)
        ->and($mes->energy_is_imported)->toBeFalse();
});

it('sabe qué meses tienen lecturas diarias detrás', function (): void {
    $turbina = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);
    app(EnergyMeterReadingService::class)
        ->record($turbina, 2_463_979, $this->user, Carbon::parse('2026-08-19'));

    expect($this->service->hasDailyReadings($this->plant, 2026, 8))->toBeTrue()
        // Julio se importó del Excel: no hay lectura ninguna detrás.
        ->and($this->service->hasDailyReadings($this->plant, 2026, 7))->toBeFalse();
});

// ── El rastro ────────────────────────────────────────────────────────────────

it('deja registrado quién corrigió y desde qué valor', function (): void {
    Queue::fake();

    $this->service->apply($this->plant, 2026, 8, [
        'processed_tons' => 3751.46, 'kwh_grid' => 1277,
        'kwh_genset' => 12363, 'kwh_turbine' => 63454,
    ]);

    // El trait despacha con `afterResponse()`, así que el job solo sale cuando la
    // petición termina; en un test hay que provocarlo.
    app()->terminate();

    // Son cifras que van a gerencia: sin rastro, una corrección legítima y un número
    // inventado se ven igual.
    Queue::assertPushed(
        WriteAuditLog::class,
        fn (WriteAuditLog $job): bool => $job->modelClass === PlantMonthlyKpi::class
            && $job->event === 'updated'
            && $job->userId === $this->user->id
            && (float) ($job->oldValues['kwh_turbine'] ?? 0) === 67160.0
            && (float) ($job->newValues['kwh_turbine'] ?? 0) === 63454.0,
    );
});
