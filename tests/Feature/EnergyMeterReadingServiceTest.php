<?php

use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create(['is_active' => true]);

    $this->meter = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->service = app(EnergyMeterReadingService::class);
});

function anotar(object $test, float $valor, string $fecha): EnergyMeterReading
{
    return $test->service->record(
        meter: $test->meter,
        readingValue: $valor,
        recordedBy: $test->user,
        readingDate: Carbon::parse($fecha),
    );
}

// ── La aritmética ────────────────────────────────────────────────────────────

it('la primera lectura es línea base, no consumo', function (): void {
    $reading = anotar($this, 2_463_979, '2026-07-31');

    // Cero y no 2.463.979: el contador llevaba años girando antes de que alguien
    // empezara a anotarlo, y eso no lo consumió la planta este mes.
    expect($reading->delta)->toBe(0.0)
        ->and($reading->previous_value)->toBeNull()
        ->and($reading->accumulated_value)->toBe(0.0);
});

it('calcula el consumo contra la lectura anterior', function (): void {
    anotar($this, 2_463_979, '2026-07-31');
    $reading = anotar($this, 2_467_685, '2026-08-02');

    expect($reading->delta)->toBe(3706.0)
        ->and($reading->previous_value)->toBe(2_463_979.0)
        ->and($reading->accumulated_value)->toBe(3706.0);
});

it('un día sin movimiento consume cero', function (): void {
    anotar($this, 2_463_979, '2026-07-31');
    anotar($this, 2_467_685, '2026-08-02');
    $reading = anotar($this, 2_467_685, '2026-08-03');

    expect($reading->delta)->toBe(0.0)
        ->and($reading->is_reset)->toBeFalse();
});

it('rechaza una lectura negativa', function (): void {
    expect(fn () => anotar($this, -5, '2026-08-01'))
        ->toThrow(InvalidArgumentException::class);
});

// ── El contador reemplazado ──────────────────────────────────────────────────

it('trata el dial que baja como contador nuevo, sin que el acumulado retroceda', function (): void {
    anotar($this, 2_463_979, '2026-07-31');
    anotar($this, 2_467_685, '2026-08-02');

    // Cambiaron el contador: el nuevo arranca casi en cero.
    $reset = anotar($this, 120, '2026-08-03');

    expect($reset->is_reset)->toBeTrue()
        // Todo lo que marca el contador nuevo es consumo desde el cambio.
        ->and($reset->delta)->toBe(120.0)
        // Y el acumulado sigue subiendo: 3.706 + 120.
        ->and($reset->accumulated_value)->toBe(3826.0);
});

// ── Corregir la serie ────────────────────────────────────────────────────────

it('rellenar un día olvidado recalcula los días siguientes', function (): void {
    anotar($this, 2_463_979, '2026-07-31');
    anotar($this, 2_472_488, '2026-08-04');

    expect(EnergyMeterReading::where('reading_date', '2026-08-04')->first()->delta)
        ->toBe(8509.0);

    // Aparece la lectura del día 2, que se había quedado sin anotar.
    anotar($this, 2_467_685, '2026-08-02');

    $dia2 = EnergyMeterReading::where('reading_date', '2026-08-02')->first();
    $dia4 = EnergyMeterReading::where('reading_date', '2026-08-04')->first();

    // El consumo se reparte entre los dos días; el total del período no cambia.
    expect($dia2->delta)->toBe(3706.0)
        ->and($dia4->delta)->toBe(4803.0)
        ->and($dia4->accumulated_value)->toBe(8509.0);
});

it('corregir una lectura del mismo día no duplica la fila', function (): void {
    anotar($this, 2_463_979, '2026-07-31');
    anotar($this, 2_467_000, '2026-08-02');
    anotar($this, 2_467_685, '2026-08-02');

    expect(EnergyMeterReading::where('reading_date', '2026-08-02')->count())->toBe(1)
        ->and(EnergyMeterReading::where('reading_date', '2026-08-02')->first()->delta)
        ->toBe(3706.0);
});

// ── La regresión que motiva el módulo ────────────────────────────────────────

it('no puede reproducir el error de la hoja de cálculo', function (): void {
    // La serie real de la turbina en agosto de 2026, tal como está en el Excel.
    $serie = [
        '2026-07-31' => 2_463_979, '2026-08-01' => 2_463_979, '2026-08-02' => 2_467_685,
        '2026-08-03' => 2_467_685, '2026-08-04' => 2_472_488, '2026-08-05' => 2_477_744,
        '2026-08-06' => 2_485_244, '2026-08-07' => 2_485_244, '2026-08-08' => 2_485_244,
        '2026-08-09' => 2_485_244, '2026-08-10' => 2_488_804, '2026-08-11' => 2_493_548,
        '2026-08-12' => 2_499_136, '2026-08-13' => 2_505_362, '2026-08-14' => 2_510_704,
        '2026-08-15' => 2_516_158, '2026-08-16' => 2_516_158, '2026-08-17' => 2_516_158,
        '2026-08-18' => 2_519_653, '2026-08-19' => 2_527_433,
    ];

    foreach ($serie as $fecha => $valor) {
        anotar($this, $valor, $fecha);
    }

    $consumo = $this->service->consumptionBetween(
        $this->meter,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    // La hoja reportó 67.160 kWh porque dos fórmulas de delta restaban la fila
    // equivocada. El acumulado dice otra cosa, y es lo que manda:
    // 2.527.433 − 2.463.979 = 63.454.
    expect($consumo)->toBe(63_454.0)
        ->and($consumo)->not->toBe(67_160.0);
});

it('el consumo del rango cuadra siempre con lo que avanzó el contador', function (): void {
    anotar($this, 1_000_000, '2026-08-01');
    anotar($this, 1_004_000, '2026-08-02');
    anotar($this, 1_009_500, '2026-08-03');

    $consumo = $this->service->consumptionBetween(
        $this->meter,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($consumo)->toBe(9500.0);
});
