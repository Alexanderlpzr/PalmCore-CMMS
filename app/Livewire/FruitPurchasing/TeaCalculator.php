<?php

namespace App\Livewire\FruitPurchasing;

use App\Domain\FruitPurchasing\Support\R100Calculator;
use App\Domain\FruitPurchasing\Support\R100Result;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Calculadora pública R-100: cualquier proveedor digita la calificación de su
 * viaje y ve la TEA a pagar, lo que recibiría y cómo ganar más. No guarda nada.
 */
class TeaCalculator extends Component
{
    /** Los cinco tipos de racimo, en el orden en que se muestran. */
    public const BUNCH_FIELDS = ['ripe', 'overripe', 'unripe', 'abnormal', 'rotten'];

    /** Opciones de limpieza de la fruta suelta: CFS = 100 % − impurezas. */
    public const LOOSE_FRUIT_QUALITY_OPTIONS = [
        '100' => 'Limpia',
        '95' => 'Con un poco de basura',
        '90' => 'Con bastante basura',
    ];

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

    public string $longStalkCount = '';

    public function mount(): void
    {
        $configuredPrice = config('palmcore.r100.oil_price_per_ton');
        $this->oilPricePerTon = $configuredPrice !== null ? $this->formatForInput((float) $configuredPrice) : '';
        $this->industrialFactorPercent = $this->formatForInput(config('palmcore.r100.industrial_factor') * 100);
    }

    /**
     * Los kilos que faltan por repartir se cuentan como racimo maduro, que es lo
     * que trae la mayoría del viaje.
     */
    public function fillRipeWithRemainder(): void
    {
        $bunchWeight = $this->number($this->bunchWeight);

        if ($bunchWeight === null || $bunchWeight <= 0) {
            return;
        }

        $others = collect(self::BUNCH_FIELDS)
            ->reject(fn (string $field): bool => $field === 'ripe')
            ->sum(fn (string $field): float => max(0.0, $this->number($this->{$field}) ?? 0.0));

        $this->ripe = $this->formatForInput(max(0.0, $bunchWeight - $others));
    }

    public function chooseLooseFruitQuality(string $percent): void
    {
        if (array_key_exists($percent, self::LOOSE_FRUIT_QUALITY_OPTIONS)) {
            $this->looseFruitQualityPercent = $percent;
        }
    }

    /**
     * Borra el viaje y deja el precio y el FACIND, que no cambian entre viajes.
     */
    public function startOver(): void
    {
        $this->reset(['bunchWeight', ...self::BUNCH_FIELDS, 'looseFruitPercent', 'looseFruitQualityPercent', 'longStalkCount']);
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

    #[Computed]
    public function distributedKilos(): float
    {
        return collect(self::BUNCH_FIELDS)->sum(fn (string $field): float => max(0.0, $this->number($this->{$field}) ?? 0.0));
    }

    /**
     * Kilos de racimo que faltan por repartir entre los tipos (negativo si sobran).
     */
    #[Computed]
    public function remainingKilos(): ?float
    {
        $bunchWeight = $this->number($this->bunchWeight);

        return $bunchWeight !== null && $bunchWeight > 0 ? $bunchWeight - $this->distributedKilos : null;
    }

    /**
     * Lo que impide calcular, dicho como se lo diría una persona.
     *
     * @return list<string>
     */
    #[Computed]
    public function problems(): array
    {
        $problems = [];

        $bunchWeight = $this->number($this->bunchWeight);
        if ($bunchWeight === null || $bunchWeight <= 0) {
            $problems[] = 'Escriba cuántos kilos pesaron los racimos.';
        }

        foreach (self::BUNCH_FIELDS as $field) {
            if (($this->number($this->{$field}) ?? 0.0) < 0) {
                $problems[] = 'Los kilos no pueden ser negativos.';
                break;
            }
        }

        $looseFruit = $this->number($this->looseFruitPercent) ?? 0.0;
        if ($looseFruit < 0 || $looseFruit >= 100) {
            $problems[] = 'La fruta suelta debe ser un número entre 0 y 99.';
        }

        $quality = $this->number($this->looseFruitQualityPercent);
        if ($quality === null || $quality < 0 || $quality > 100) {
            $problems[] = 'Elija qué tan limpia venía la fruta suelta.';
        }

        if ($this->number($this->industrialFactorPercent) === null) {
            $problems[] = 'Falta el costo de la planta (FACIND).';
        }

        $longStalks = $this->number($this->longStalkCount) ?? 0.0;
        if ($longStalks < 0 || floor($longStalks) !== $longStalks) {
            $problems[] = 'Los racimos con tallo largo se cuentan enteros: 0, 1, 2…';
        }

        if (($this->number($this->oilPricePerTon) ?? 0.0) < 0) {
            $problems[] = 'El precio no puede ser negativo.';
        }

        return $problems;
    }

    #[Computed]
    public function result(): ?R100Result
    {
        if ($this->problems !== []) {
            return null;
        }

        return R100Calculator::fromConfig()->calculate(...$this->calculatorArguments());
    }

    /**
     * @return array{level: string, label: string, message: string}|null
     */
    #[Computed]
    public function quality(): ?array
    {
        if ($this->result === null || ! $this->weightsBalanced) {
            return null;
        }

        $levels = config('palmcore.r100.quality_levels');

        return match (true) {
            $this->result->tea >= $levels['excellent'] => ['level' => 'excellent', 'label' => 'Excelente', 'message' => 'Su fruta llegó en muy buen estado. ¡Siga así!'],
            $this->result->tea >= $levels['good'] => ['level' => 'good', 'label' => 'Buena', 'message' => 'Va bien. Con unos ajustes puede ganar más.'],
            default => ['level' => 'improve', 'label' => 'Puede mejorar', 'message' => 'Hay plata quedándose en el camino. Mire los consejos.'],
        };
    }

    #[Computed]
    public function weightsBalanced(): bool
    {
        return $this->result !== null && $this->result->weightsAreBalanced($this->number($this->bunchWeight));
    }

    /**
     * Consejos para el próximo viaje. Cuando se puede, cuánto ganaría con cada uno:
     * se recalcula el viaje con el cambio y se compara.
     *
     * @return list<array{key: string, title: string, text: string, teaGain: float|null, moneyGain: float|null, gainLabel: string}>
     */
    #[Computed]
    public function tips(): array
    {
        if ($this->result === null || ! $this->weightsBalanced) {
            return [];
        }

        $tips = [];
        $unripe = $this->number($this->unripe) ?? 0.0;
        $overripeAndRotten = ($this->number($this->overripe) ?? 0.0) + ($this->number($this->rotten) ?? 0.0);
        $looseFruit = $this->number($this->looseFruitPercent) ?? 0.0;
        $quality = $this->number($this->looseFruitQualityPercent) ?? 100.0;
        $longStalks = $this->number($this->longStalkCount) ?? 0.0;

        if ($unripe > 0) {
            $tips[] = $this->tip('water', 'Aguaterapia a los racimos verdes', 'Moje con agua los racimos verdes masudos unos minutos y déjelos uno o dos días hasta que suelten fruto. Llegan como maduros y valen más.',
                ['ripe' => ($this->number($this->ripe) ?? 0.0) + $unripe, 'unripe' => 0.0]);
        }

        if ($overripeAndRotten > 0) {
            $tips[] = $this->tip('stick', 'Paloterapia a los sobremaduros y podridos', 'Meta el racimo en un saco y golpéelo para que suelte el fruto. Deje el raquis vacío en el campo y envíe solo el fruto suelto: así no pierde puntos.', null);
        }

        if ($longStalks > 0) {
            $tips[] = $this->tip('stalk', 'Corte el tallo al ras', 'Los racimos con pedúnculo largo le descuentan. Córtelos cerca del racimo antes de cargar.',
                ['longStalkCount' => 0]);
        }

        if ($looseFruit > 0 && $quality < 100) {
            $tips[] = $this->tip('clean', 'Envíe la fruta suelta limpia', 'Sin hojas, palos ni tierra. La basura le resta valor a cada kilo de fruta suelta.',
                ['looseFruitQuality' => 1.0]);
        }

        if ($looseFruit < 99) {
            $tips[] = $this->tip('pick', 'Recoja la fruta suelta del suelo', 'Cada pepa cuenta: por cada 1 % más de fruta suelta en el viaje, su TEA sube 0,4 puntos. Recójala antes de 3 días.',
                ['looseFruitShare' => min(0.98, ($looseFruit + 1) / 100)], 'Por cada 1 % más');
        }

        return $tips;
    }

    #[Computed]
    public function whatsappUrl(): ?string
    {
        if ($this->result === null || ! $this->weightsBalanced) {
            return null;
        }

        $lines = [
            '*Método R-100*',
            'Peso de racimos: '.number_format($this->number($this->bunchWeight), 0, ',', '.').' kg',
            'Rendimiento (TEA): '.number_format($this->result->tea * 100, 2, ',', '.').' %'.($this->quality ? ' — '.$this->quality['label'] : ''),
        ];

        if ($this->result->tripValue !== null) {
            $lines[] = 'Valor estimado del viaje: $ '.number_format($this->result->tripValue, 0, ',', '.');
        }

        $lines[] = 'Calcule el suyo: '.route('r100.calculator');

        return 'https://wa.me/?text='.rawurlencode(implode("\n", $lines));
    }

    public function render(): View
    {
        return view('livewire.fruit-purchasing.tea-calculator');
    }

    /**
     * @param  array<string, float|int>|null  $changes  argumentos de la calculadora que cambian si se sigue el consejo
     * @return array{key: string, title: string, text: string, teaGain: float|null, moneyGain: float|null, gainLabel: string}
     */
    private function tip(string $key, string $title, string $text, ?array $changes, string $gainLabel = 'Podría ganar'): array
    {
        $teaGain = null;
        $moneyGain = null;

        if ($changes !== null) {
            $improved = R100Calculator::fromConfig()->calculate(...array_merge($this->calculatorArguments(), $changes));
            $teaGain = $improved->tea - $this->result->tea;
            $moneyGain = $improved->tripValue !== null ? $improved->tripValue - $this->result->tripValue : null;
        }

        return compact('key', 'title', 'text', 'teaGain', 'moneyGain', 'gainLabel');
    }

    /**
     * @return array{bunchWeight: float, ripe: float, overripe: float, unripe: float, abnormal: float, rotten: float, looseFruitShare: float, looseFruitQuality: float, industrialFactor: float, longStalkCount: int, oilPricePerTon: float|null}
     */
    private function calculatorArguments(): array
    {
        return [
            'bunchWeight' => $this->number($this->bunchWeight),
            'ripe' => $this->number($this->ripe) ?? 0.0,
            'overripe' => $this->number($this->overripe) ?? 0.0,
            'unripe' => $this->number($this->unripe) ?? 0.0,
            'abnormal' => $this->number($this->abnormal) ?? 0.0,
            'rotten' => $this->number($this->rotten) ?? 0.0,
            'looseFruitShare' => ($this->number($this->looseFruitPercent) ?? 0.0) / 100,
            'looseFruitQuality' => $this->number($this->looseFruitQualityPercent) / 100,
            'industrialFactor' => $this->number($this->industrialFactorPercent) / 100,
            'longStalkCount' => (int) ($this->number($this->longStalkCount) ?? 0),
            'oilPricePerTon' => $this->number($this->oilPricePerTon),
        ];
    }

    /**
     * Lee el número como se escribe en Colombia: «18.000» son dieciocho mil y
     * «4,7» es cuatro coma siete. También acepta «18000», «4.7» y «$ 4.000.000».
     */
    private function number(string $value): ?float
    {
        $normalized = str_replace([' ', '$', "\u{00A0}"], '', trim($value));

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $normalized) === 1) {
            $normalized = str_replace('.', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function formatForInput(float $value): string
    {
        if (floor($value) === $value) {
            return number_format($value, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
