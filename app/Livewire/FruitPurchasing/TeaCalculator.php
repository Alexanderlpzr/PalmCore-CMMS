<?php

namespace App\Livewire\FruitPurchasing;

use App\Domain\FruitPurchasing\Support\R100Calculator;
use App\Domain\FruitPurchasing\Support\R100Result;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Calculadora pública R-100: cualquier proveedor digita la calificación de su
 * viaje y ve la TEA a pagar y lo que recibiría. No guarda nada.
 */
class TeaCalculator extends Component
{
    public string $oilPricePerTon = '';

    public string $bunchWeight = '';

    public string $ripe = '';

    public string $overripe = '';

    public string $unripe = '';

    public string $abnormal = '';

    public string $rotten = '';

    public string $looseFruitPercent = '';

    public string $looseFruitQualityPercent = '100';

    public string $industrialFactorPercent = '';

    public string $longStalkCount = '0';

    public function mount(): void
    {
        $configuredPrice = config('palmcore.r100.oil_price_per_ton');
        $this->oilPricePerTon = $configuredPrice !== null ? (string) $configuredPrice : '';
        $this->industrialFactorPercent = (string) (config('palmcore.r100.industrial_factor') * 100);
    }

    /**
     * Valores de la ecuación por tipo de racimo, para mostrarlos junto a cada campo.
     *
     * @return array{ripe: float, overripe: float, unripe: float, abnormal: float, rotten: float}
     */
    #[Computed]
    public function bunchValues(): array
    {
        return config('palmcore.r100.bunch_values');
    }

    /**
     * Lo que impide calcular: campos vacíos, negativos o fuera de rango.
     *
     * @return list<string>
     */
    #[Computed]
    public function problems(): array
    {
        $problems = [];

        if ($this->number($this->bunchWeight) === null || $this->number($this->bunchWeight) <= 0) {
            $problems[] = 'Digite el total peso de racimos (TPR).';
        }

        $kiloFields = [
            'ripe' => 'Racimo Maduro (RM)',
            'overripe' => 'Racimo Sobre Maduro (RSM)',
            'unripe' => 'Racimo Verde Masudo (RVM)',
            'abnormal' => 'Racimo Verde Anormal (RVA)',
            'rotten' => 'Racimo Podrido (RP)',
        ];

        foreach ($kiloFields as $field => $label) {
            if ($this->number($this->{$field}) < 0) {
                $problems[] = "El peso de {$label} no puede ser negativo.";
            }
        }

        $looseFruit = $this->number($this->looseFruitPercent) ?? 0.0;
        if ($looseFruit < 0 || $looseFruit >= 100) {
            $problems[] = 'El % de fruta suelta debe estar entre 0 y 99.';
        }

        $quality = $this->number($this->looseFruitQualityPercent);
        if ($quality === null || $quality < 0 || $quality > 100) {
            $problems[] = 'La calidad de fruta suelta (CFS) debe estar entre 0 y 100 %.';
        }

        if ($this->number($this->industrialFactorPercent) === null) {
            $problems[] = 'Digite el FACIND.';
        }

        $longStalks = $this->number($this->longStalkCount) ?? 0.0;
        if ($longStalks < 0 || floor($longStalks) !== $longStalks) {
            $problems[] = 'La cantidad de pedúnculo largo debe ser un número entero, sin decimales.';
        }

        $price = $this->number($this->oilPricePerTon);
        if ($price !== null && $price < 0) {
            $problems[] = 'El precio de la tonelada no puede ser negativo.';
        }

        return $problems;
    }

    #[Computed]
    public function result(): ?R100Result
    {
        if ($this->problems !== []) {
            return null;
        }

        return R100Calculator::fromConfig()->calculate(
            bunchWeight: $this->number($this->bunchWeight),
            ripe: $this->number($this->ripe) ?? 0.0,
            overripe: $this->number($this->overripe) ?? 0.0,
            unripe: $this->number($this->unripe) ?? 0.0,
            abnormal: $this->number($this->abnormal) ?? 0.0,
            rotten: $this->number($this->rotten) ?? 0.0,
            looseFruitShare: ($this->number($this->looseFruitPercent) ?? 0.0) / 100,
            looseFruitQuality: $this->number($this->looseFruitQualityPercent) / 100,
            industrialFactor: $this->number($this->industrialFactorPercent) / 100,
            longStalkCount: (int) ($this->number($this->longStalkCount) ?? 0),
            oilPricePerTon: $this->number($this->oilPricePerTon),
        );
    }

    public function render(): View
    {
        return view('livewire.fruit-purchasing.tea-calculator');
    }

    /**
     * Convierte lo digitado en número; acepta coma decimal. Vacío o texto → null.
     */
    private function number(string $value): ?float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
