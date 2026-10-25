<?php

use App\Domain\FruitPurchasing\Support\R100Calculator;

/**
 * Casos tomados de «Solo Formula de pago.xlsx», hoja GUARIAPALMA (ejemplos de la
 * diapositiva 11), que ya usaba la fórmula oficial: RM 21 % y FACIND 4,7 %.
 */
it('reproduce la TEA del ejemplo 3 de la presentación (20,24 %)', function () {
    $result = R100Calculator::fromConfig()->calculate(
        bunchWeight: 18000, ripe: 17890, overripe: 30, unripe: 0, abnormal: 80, rotten: 0,
        looseFruitShare: 0.10, looseFruitQuality: 1.0, industrialFactor: 0.047, longStalkCount: 0,
    );

    expect($result->tea)->toEqualWithDelta(0.2023694, 0.000001)
        ->and($result->bunchContribution)->toEqualWithDelta(0.2093694, 0.000001)
        ->and($result->looseFruitContribution)->toEqualWithDelta(0.04, 0.000001)
        ->and($result->weightGap)->toEqualWithDelta(0.0, 0.000001)
        ->and($result->totalFruitKilos)->toEqualWithDelta(20000, 0.001)
        ->and($result->tripValue)->toBeNull();
});

it('reproduce la TEA del ejemplo 2 con aguaterapia y paloterapia (19,60 %)', function () {
    $result = R100Calculator::fromConfig()->calculate(
        bunchWeight: 18156, ripe: 17964.986, overripe: 0, unripe: 0, abnormal: 191, rotten: 0,
        looseFruitShare: 1794 / 19950, looseFruitQuality: 0.95, industrialFactor: 0.047, longStalkCount: 0,
    );

    expect($result->tea)->toEqualWithDelta(0.1960141, 0.000001);
});

/**
 * «Formula pago.xlsx», fórmula definitiva del director (2026-10-24): igual a la
 * anterior pero con FACIND 4,1 %. Hoja GUARIAPALMA, fila 19 (TEA A PAGAR).
 */
it('reproduce la TEA de la fórmula definitiva del director con FACIND 4,1 %', function (array $trip, float $expectedTea) {
    expect(config('palmcore.r100.industrial_factor'))->toBe(0.041);

    $result = R100Calculator::fromConfig()->calculate(
        ...$trip,
        industrialFactor: config('palmcore.r100.industrial_factor'),
        longStalkCount: 0,
    );

    expect($result->tea)->toEqualWithDelta($expectedTea, 0.0000001);
})->with([
    'ejemplo 2 (C19)' => [['bunchWeight' => 18953.4, 'ripe' => 18060, 'overripe' => 259, 'unripe' => 52.7949860724234, 'abnormal' => 193.5816155988858, 'rotten' => 387.1632311977716, 'looseFruitShare' => 0.07, 'looseFruitQuality' => 0.95], 0.18904360687242505],
    'ejemplo 2 con terapia (D19)' => [['bunchWeight' => 18845.4, 'ripe' => 18112.794986072422, 'overripe' => 0, 'unripe' => 0, 'abnormal' => 191, 'rotten' => 0, 'looseFruitShare' => 1534.6 / 20380, 'looseFruitQuality' => 0.95], 0.1904636093299985],
    'ejemplo 3 (E19)' => [['bunchWeight' => 18000, 'ripe' => 17890, 'overripe' => 30, 'unripe' => 0, 'abnormal' => 80, 'rotten' => 0, 'looseFruitShare' => 0.10, 'looseFruitQuality' => 1.0], 0.20836944444444444],
    'viaje de 20.000 kg (I19)' => [['bunchWeight' => 18600, 'ripe' => 17700, 'overripe' => 300, 'unripe' => 200, 'abnormal' => 200, 'rotten' => 200, 'looseFruitShare' => 0.07, 'looseFruitQuality' => 0.95], 0.18987419354838714],
]);

it('valora el viaje completo a TEA por el precio de la tonelada de aceite', function () {
    $result = R100Calculator::fromConfig()->calculate(
        bunchWeight: 18000, ripe: 17890, overripe: 30, unripe: 0, abnormal: 80, rotten: 0,
        looseFruitShare: 0.10, looseFruitQuality: 1.0, industrialFactor: 0.047, longStalkCount: 0,
        oilPricePerTon: 4_000_000,
    );

    expect($result->fruitPricePerTon)->toEqualWithDelta(809_477.78, 0.01)
        ->and($result->tripValue)->toEqualWithDelta(16_189_555.56, 0.01);
});

it('descuenta el pedúnculo largo como fracción del peso de racimos, sin dividir otra vez entre 100', function () {
    $withoutStalks = R100Calculator::fromConfig()->calculate(
        bunchWeight: 18000, ripe: 18000, overripe: 0, unripe: 0, abnormal: 0, rotten: 0,
        looseFruitShare: 0, looseFruitQuality: 1.0, industrialFactor: 0.047, longStalkCount: 0,
    );
    $withStalks = R100Calculator::fromConfig()->calculate(
        bunchWeight: 18000, ripe: 18000, overripe: 0, unripe: 0, abnormal: 0, rotten: 0,
        looseFruitShare: 0, looseFruitQuality: 1.0, industrialFactor: 0.047, longStalkCount: 12,
    );

    // 12 pedúnculos × 1,5 kg / 18.000 kg = 0,1 puntos de TEA.
    expect($withStalks->longStalkPenalty)->toEqualWithDelta(0.001, 0.0000001)
        ->and($withoutStalks->tea - $withStalks->tea)->toEqualWithDelta(0.001, 0.0000001);
});

it('reporta cuando los tipos de racimo no suman el TPR', function () {
    $result = R100Calculator::fromConfig()->calculate(
        bunchWeight: 18000, ripe: 17000, overripe: 0, unripe: 0, abnormal: 0, rotten: 0,
        looseFruitShare: 0, looseFruitQuality: 1.0, industrialFactor: 0.047, longStalkCount: 0,
    );

    expect($result->weightGap)->toEqualWithDelta(-1000, 0.001)
        ->and($result->weightsAreBalanced(18000))->toBeFalse();
});

it('rechaza un peso de racimos en cero', function () {
    R100Calculator::fromConfig()->calculate(
        bunchWeight: 0, ripe: 0, overripe: 0, unripe: 0, abnormal: 0, rotten: 0,
        looseFruitShare: 0, looseFruitQuality: 1.0, industrialFactor: 0.047, longStalkCount: 0,
    );
})->throws(InvalidArgumentException::class);
