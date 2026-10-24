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
