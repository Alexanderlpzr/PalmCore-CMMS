<?php

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use App\Models\Tenant;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->csv = tempnam(sys_get_temp_dir(), 'energia').'.csv';
});

afterEach(function (): void {
    if (is_file($this->csv)) {
        unlink($this->csv);
    }
});

function escribirCsv(string $ruta, string $contenido): void
{
    file_put_contents($ruta, "anio,mes,kwh_red,kwh_planta,kwh_turbina,rff_toneladas,nota\n".$contenido);
}

it('carga los meses y los marca como importados', function (): void {
    escribirCsv($this->csv, "2026,1,13828,31115,118117,,\n2026,2,8002,46351,71970,,\n");

    $this->artisan('energy:import-history', ['file' => $this->csv])
        ->assertSuccessful();

    $enero = PlantMonthlyKpi::withoutGlobalScopes()
        ->where('plant_id', $this->plant->id)->where('year', 2026)->where('month', 1)->first();

    expect($enero->kwh_grid)->toBe(13828.0)
        ->and($enero->kwh_genset)->toBe(31115.0)
        ->and($enero->kwh_turbine)->toBe(118117.0)
        ->and($enero->energy_is_imported)->toBeTrue()
        // Las tres columnas calculadas salen solas de Postgres.
        ->and($enero->kwh_total)->toBe(163060.0);
});

it('deja en NULL la turbina que la hoja no trae, en vez de ponerla en cero', function (): void {
    // Enero de 2025: la hoja trae un guion en turbina. Cero afirmaría que la planta
    // funcionó a diésel; NULL dice que no lo sabemos, que es la verdad.
    escribirCsv($this->csv, "2025,1,9240,117981,,,\n");

    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    $mes = PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2025)->where('month', 1)->first();

    expect($mes->kwh_turbine)->toBeNull()
        // El total sí se puede afirmar: es lo que se pagó y lo que se quemó.
        ->and($mes->kwh_total)->toBe(127221.0)
        // Pero el porcentaje de energía limpia no, y por eso queda nulo.
        ->and($mes->clean_energy_percentage)->toBeNull();
});

it('no carga los meses sin ninguna cifra', function (): void {
    escribirCsv($this->csv, "2026,8,1277,12363,63454,,\n2026,9,,,,,\n2026,10,,,,,\n");

    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    expect(PlantMonthlyKpi::withoutGlobalScopes()->count())->toBe(1)
        ->and(PlantMonthlyKpi::withoutGlobalScopes()->where('month', 9)->exists())->toBeFalse();
});

it('es idempotente: reexportar y volver a correr no duplica', function (): void {
    escribirCsv($this->csv, "2026,1,13828,31115,118117,,\n");

    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();
    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    expect(PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2026)->where('month', 1)->count())
        ->toBe(1);
});

it('calcula el KWh/RFF contra las toneladas que ya tiene el mes', function (): void {
    // El mes ya estaba cerrado con su fruta: el denominador no se captura dos veces.
    PlantMonthlyKpi::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
        'year' => 2026,
        'month' => 1,
        'processed_tons' => 5320,
        'calculated_at' => now(),
    ]);

    escribirCsv($this->csv, "2026,1,13828,31115,118117,,\n");
    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    $mes = PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2026)->where('month', 1)->first();

    // 163.060 / 5.320 = 30,65 kWh por tonelada, la cifra de la hoja.
    expect($mes->kwh_per_ton)->toBe(30.65)
        // 118.117 / 163.060 = 72,44 % de energía limpia.
        ->and($mes->clean_energy_percentage)->toBe(72.44);
});

it('rechaza un CSV sin las columnas que necesita', function (): void {
    file_put_contents($this->csv, "anio,mes,kilovatios\n2026,1,100\n");

    $this->artisan('energy:import-history', ['file' => $this->csv])
        ->assertFailed();
});

it('avisa y sigue cuando una fila trae un período imposible', function (): void {
    escribirCsv($this->csv, "2026,13,100,200,300,,\n2026,1,13828,31115,118117,,\n");

    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    expect(PlantMonthlyKpi::withoutGlobalScopes()->count())->toBe(1);
});

// ── La fruta, que es el denominador ──────────────────────────────────────────

it('carga las toneladas y con ellas aparece el KWh/RFF', function (): void {
    escribirCsv($this->csv, '2026,1,13828,31115,118117,5320,
');

    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    $mes = PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2026)->where('month', 1)->first();

    expect($mes->processed_tons)->toBe(5320.0)
        // 163.060 / 5.320 = 30,65 kWh por tonelada, la cifra de la hoja.
        ->and($mes->kwh_per_ton)->toBe(30.65)
        // Marcada como manual: son totales del mes, sin los días detrás.
        ->and($mes->processed_tons_is_manual)->toBeTrue();
});

it('deja el mes sin fruta sin denominador, en vez de inventarlo', function (): void {
    // Agosto de 2026 no trae RFF en la hoja: por eso ahí el Excel muestra #DIV/0!.
    escribirCsv($this->csv, '2026,8,1277,12363,63454,,
');

    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    $mes = PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2026)->where('month', 8)->first();

    expect($mes->kwh_total)->toBe(77094.0)
        ->and($mes->kwh_per_ton)->toBeNull()
        ->and($mes->processed_tons_is_manual)->toBeFalse();
});

it('el cierre mensual no pisa la fruta importada', function (): void {
    escribirCsv($this->csv, '2026,1,13828,31115,118117,5320,
');
    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    // Sin la marca de manual, recalcular el mes buscaría los días en el calendario de
    // producción, no encontraría ninguno, y dejaría la fruta en cero.
    app(PlantKpiService::class)->snapshotMonth($this->plant, 2026, 1);

    $mes = PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2026)->where('month', 1)->first();

    expect($mes->processed_tons)->toBe(5320.0)
        ->and($mes->kwh_per_ton)->toBe(30.65);
});

it('sigue aceptando un CSV sin la columna de fruta', function (): void {
    file_put_contents($this->csv, "anio,mes,kwh_red,kwh_planta,kwh_turbina\n2026,1,13828,31115,118117\n");

    $this->artisan('energy:import-history', ['file' => $this->csv])->assertSuccessful();

    expect(PlantMonthlyKpi::withoutGlobalScopes()->where('year', 2026)->first()->kwh_total)
        ->toBe(163060.0);
});
