<?php

namespace App\Domain\FruitPurchasing\Support;

use InvalidArgumentException;

/**
 * Fórmula R-100 de valoración de la fruta:
 *
 *   TEA = (RM·21% + RSM·12,5% + RVM·11% + RVA·10% + RP·1,5%) / TPR
 *         + %FS·40%·%CFS − %FACIND − (NPL·1,5 / TPR)%
 *
 * Igual que la celda del Excel del director, el castigo por pedúnculo largo
 * lleva el «%» final: la fracción NPL·1,5/TPR se divide otra vez entre 100.
 *
 * El viaje completo (racimos + fruta suelta) se paga a TEA × precio de la
 * tonelada de aceite. Los porcentajes entran en fracción (8 % = 0,08).
 */
class R100Calculator
{
    /**
     * @param  array{ripe: float, overripe: float, unripe: float, abnormal: float, rotten: float}  $bunchValues
     */
    public function __construct(
        private array $bunchValues,
        private float $looseFruitValue,
        private float $kilosPerLongStalk,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            bunchValues: config('palmcore.r100.bunch_values'),
            looseFruitValue: (float) config('palmcore.r100.loose_fruit_value'),
            kilosPerLongStalk: (float) config('palmcore.r100.kilos_per_long_stalk'),
        );
    }

    public function calculate(
        float $bunchWeight,
        float $ripe,
        float $overripe,
        float $unripe,
        float $abnormal,
        float $rotten,
        float $looseFruitShare,
        float $looseFruitQuality,
        float $industrialFactor,
        int $longStalkCount,
        ?float $oilPricePerTon = null,
    ): R100Result {
        if ($bunchWeight <= 0) {
            throw new InvalidArgumentException('El peso de racimos (TPR) debe ser mayor que cero.');
        }

        if ($looseFruitShare < 0 || $looseFruitShare >= 1) {
            throw new InvalidArgumentException('El % de fruta suelta debe estar entre 0 y 100 %.');
        }

        $bunchContribution = (
            $ripe * $this->bunchValues['ripe']
            + $overripe * $this->bunchValues['overripe']
            + $unripe * $this->bunchValues['unripe']
            + $abnormal * $this->bunchValues['abnormal']
            + $rotten * $this->bunchValues['rotten']
        ) / $bunchWeight;

        $looseFruitContribution = $looseFruitShare * $this->looseFruitValue * $looseFruitQuality;
        $longStalkPenalty = $longStalkCount * $this->kilosPerLongStalk / $bunchWeight / 100;
        $tea = $bunchContribution + $looseFruitContribution - $industrialFactor - $longStalkPenalty;

        // %FS es la parte del viaje que llega suelta, así que el viaje pesa TPR / (1 − %FS).
        $totalFruitKilos = $bunchWeight / (1 - $looseFruitShare);

        $fruitPricePerTon = $oilPricePerTon !== null ? $tea * $oilPricePerTon : null;
        $tripValue = $fruitPricePerTon !== null ? $fruitPricePerTon * $totalFruitKilos / 1000 : null;

        return new R100Result(
            bunchContribution: $bunchContribution,
            looseFruitContribution: $looseFruitContribution,
            industrialFactor: $industrialFactor,
            longStalkPenalty: $longStalkPenalty,
            tea: $tea,
            totalFruitKilos: $totalFruitKilos,
            weightGap: ($ripe + $overripe + $unripe + $abnormal + $rotten) - $bunchWeight,
            fruitPricePerTon: $fruitPricePerTon,
            tripValue: $tripValue,
        );
    }
}
