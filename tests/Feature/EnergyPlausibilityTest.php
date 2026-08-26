<?php

use App\Domain\Energy\Services\EnergyMeterReadingService;
use App\Exceptions\BusinessRuleException;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\Plant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * El dígito de más.
 *
 * Un contador acumulado crece sin límite, así que no hay número máximo que ponerle: el
 * tope absoluto que protege a las toneladas aquí no sirve. Pero teclear 24.637.790 en vez
 * de 2.463.979 convierte un día de 5.000 kWh en uno de 22 millones, y al día siguiente la
 * lectura correcta se lee como contador reemplazado y vuelve a contar entera. Dos meses
 * arruinados por una tecla.
 *
 * La única defensa posible es comparar el consumo con lo que ese aparato acostumbra.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->plant = Plant::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create(['is_active' => true]);

    $this->meter = EnergyMeter::factory()->turbine()->create([
        'tenant_id' => $this->tenant->id,
        'plant_id' => $this->plant->id,
    ]);

    $this->service = app(EnergyMeterReadingService::class);

    // La serie real de la turbina: consumos diarios entre 3.500 y 7.800 kWh.
    $serie = [
        '2026-08-10' => 2_488_804, '2026-08-11' => 2_493_548, '2026-08-12' => 2_499_136,
        '2026-08-13' => 2_505_362, '2026-08-14' => 2_510_704, '2026-08-15' => 2_516_158,
        '2026-08-18' => 2_519_653, '2026-08-19' => 2_527_433,
    ];

    foreach ($serie as $fecha => $valor) {
        $this->service->record($this->meter, $valor, $this->user, Carbon::parse($fecha));
    }
});

it('rechaza la lectura con un dígito de más', function (): void {
    // 25.274.330 en vez de 2.527.433: el dedo se fue en el teclado numérico.
    expect(fn () => $this->service->record(
        $this->meter, 25_274_330, $this->user, Carbon::parse('2026-08-20')
    ))->toThrow(BusinessRuleException::class);

    expect(EnergyMeterReading::where('reading_date', '2026-08-20')->exists())->toBeFalse();
});

it('explica el aviso con las dos cifras que hay que comparar', function (): void {
    $aviso = $this->service->implausibilityWarning(
        $this->meter, 25_274_330, Carbon::parse('2026-08-20')
    );

    expect($aviso)
        ->toContain('en un día')
        ->toContain('lo habitual')
        ->toContain('sobre un dígito');
});

it('deja pasar un día fuerte de verdad', function (): void {
    // 9.000 kWh: por encima de la mediana, pero es un día de planta a tope, no un dedo.
    $lectura = $this->service->record(
        $this->meter, 2_536_433, $this->user, Carbon::parse('2026-08-20')
    );

    expect($lectura->delta)->toBe(9000.0);
});

it('deja pasar un día de planta parada', function (): void {
    // Solo mira hacia arriba: un consumo bajo es información legítima y frecuente.
    $lectura = $this->service->record(
        $this->meter, 2_527_433, $this->user, Carbon::parse('2026-08-20')
    );

    expect($lectura->delta)->toBe(0.0);
});

it('guarda la lectura rara cuando alguien la confirma', function (): void {
    $lectura = $this->service->record(
        $this->meter, 25_274_330, $this->user, Carbon::parse('2026-08-20'), force: true
    );

    expect($lectura->delta)->toBe(22_746_897.0);
});

it('no opina sobre un contador recién puesto en servicio', function (): void {
    $nuevo = EnergyMeter::factory()->grid()->create([
        'tenant_id' => $this->tenant->id, 'plant_id' => $this->plant->id,
    ]);

    $this->service->record($nuevo, 1000, $this->user, Carbon::parse('2026-08-01'));
    $this->service->record($nuevo, 1100, $this->user, Carbon::parse('2026-08-02'));

    // Con dos días no hay «lo habitual» de nada: el guardia calla en vez de estorbar.
    $salto = $this->service->record($nuevo, 900_000, $this->user, Carbon::parse('2026-08-03'));

    expect($salto->delta)->toBe(898_900.0);
});

it('no confunde un contador reemplazado con un error de tecla', function (): void {
    // El dial nuevo arranca casi en cero: eso es un reset, no un salto hacia arriba.
    $reset = $this->service->record(
        $this->meter, 150, $this->user, Carbon::parse('2026-08-20')
    );

    expect($reset->is_reset)->toBeTrue()
        ->and($reset->delta)->toBe(150.0);
});

it('sigue avisando aunque una lectura mala ya se haya colado', function (): void {
    // Se mide contra la mediana y no contra el promedio justo por esto: una sola lectura
    // enorme se llevaría el promedio consigo y el guardia dejaría de avisar cuando más
    // falta hace.
    $this->service->record($this->meter, 25_274_330, $this->user, Carbon::parse('2026-08-20'), force: true);

    expect(fn () => $this->service->record(
        $this->meter, 250_000_000, $this->user, Carbon::parse('2026-08-21')
    ))->toThrow(BusinessRuleException::class);
});
