<?php

namespace App\Domain\FruitPurchasing\Support;

/**
 * Resultado de calificar un viaje con la fórmula R-100. Los aportes vienen en
 * fracción (0,2024 = 20,24 % de TEA) y los pesos en kilos.
 */
final readonly class R100Result
{
    public function __construct(
        public float $bunchContribution,
        public float $looseFruitContribution,
        public float $industrialFactor,
        public float $longStalkPenalty,
        public float $tea,
        public float $totalFruitKilos,
        public float $weightGap,
        public ?float $fruitPricePerTon,
        public ?float $tripValue,
    ) {}

    /**
     * Los cinco tipos de racimo deben sumar el peso de racimos (TPR). Más del 1 %
     * de diferencia indica un dato mal digitado y la TEA no es confiable.
     */
    public function weightsAreBalanced(float $bunchWeight): bool
    {
        return abs($this->weightGap) <= 0.01 * $bunchWeight;
    }
}
