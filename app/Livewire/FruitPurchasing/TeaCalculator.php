<?php

namespace App\Livewire\FruitPurchasing;

use App\Domain\FruitPurchasing\Services\OilPriceService;
use App\Domain\FruitPurchasing\Support\ColombianNumber;
use App\Domain\FruitPurchasing\Support\R100Calculator;
use App\Domain\FruitPurchasing\Support\R100Result;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Calculadora pública R-100: cualquier proveedor digita lo que le dieron en
 * báscula y tolva y ve la TEA a pagar, lo que recibiría y cómo ganar más.
 *
 * La báscula da el peso neto del viaje y el de la fruta suelta; el peso de
 * racimos (TPR) es la diferencia. La tolva da el % de impureza de la fruta
 * suelta; su calidad (CFS) es 100 % menos esa impureza. El precio del aceite
 * lo publica un super admin en /r100/admin y aquí solo se lee. No guarda nada.
 */
class TeaCalculator extends Component
{
    /** Los cinco tipos de racimo, en el orden en que se muestran. */
    public const BUNCH_FIELDS = ['ripe', 'overripe', 'unripe', 'abnormal', 'rotten'];

    public string $netWeight = '';

    public string $looseFruitKilos = '';

    public string $impurityPercent = '';

    public string $ripe = '';

    public string $overripe = '';

    public string $unripe = '';

    public string $abnormal = '';

    public string $rotten = '';

    public string $longStalkCount = '';

    /**
     * El precio publicado de la tonelada de aceite, o null si todavía no hay.
     *
     * @return array{price: float, updatedAt: Carbon|null, updatedBy: string|null}|null
     */
    #[Computed]
    public function oilPrice(): ?array
    {
        return app(OilPriceService::class)->current();
    }

    /**
     * Los kilos que faltan por repartir se cuentan como racimo maduro, que es lo
     * que trae la mayoría del viaje.
     */
    public function fillRipeWithRemainder(): void
    {
        if ($this->bunchWeight === null || $this->bunchWeight <= 0) {
            return;
        }

        $others = collect(self::BUNCH_FIELDS)
            ->reject(fn (string $field): bool => $field === 'ripe')
            ->sum(fn (string $field): float => max(0.0, $this->number($this->{$field}) ?? 0.0));

        $this->ripe = ColombianNumber::format(max(0.0, $this->bunchWeight - $others));
    }

    /**
     * Borra el viaje y deja el FACIND, que no cambia entre viajes.
     */
    public function startOver(): void
    {
        $this->reset(['netWeight', 'looseFruitKilos', 'impurityPercent', ...self::BUNCH_FIELDS, 'longStalkCount']);
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
     * Peso de racimos (TPR) = peso neto del viaje − fruta suelta. Null mientras
     * falte el neto o la fruta suelta pese tanto como el viaje.
     */
    #[Computed]
    public function bunchWeight(): ?float
    {
        $netWeight = $this->number($this->netWeight);

        if ($netWeight === null || $netWeight <= 0) {
            return null;
        }

        $bunchWeight = $netWeight - max(0.0, $this->number($this->looseFruitKilos) ?? 0.0);

        return $bunchWeight > 0 ? $bunchWeight : null;
    }

    /**
     * Parte del viaje que llegó como fruta suelta (0,10 = 10 %).
     */
    #[Computed]
    public function looseFruitShare(): ?float
    {
        $netWeight = $this->number($this->netWeight);

        return $netWeight !== null && $netWeight > 0
            ? max(0.0, $this->number($this->looseFruitKilos) ?? 0.0) / $netWeight
            : null;
    }

    /**
     * Qué pasos ya están listos, para pintarlos en verde con ✔. El 2 (fruta
     * suelta) es opcional: queda listo apenas el 1 lo está y sus campos no tienen
     * errores. El 3 queda listo cuando los tipos de racimo cuadran con el TPR.
     *
     * @return array{1: bool, 2: bool, 3: bool}
     */
    #[Computed]
    public function completedSteps(): array
    {
        $netWeight = $this->number($this->netWeight);
        $netWeightReady = $netWeight !== null && $netWeight > 0;

        $looseFruit = $this->number($this->looseFruitKilos) ?? 0.0;
        $impurity = $this->number($this->impurityPercent) ?? 0.0;
        $looseFruitReady = $netWeightReady
            && $looseFruit >= 0 && $looseFruit < $netWeight
            && $impurity >= 0 && $impurity <= 100;

        return [
            1 => $netWeightReady,
            2 => $looseFruitReady,
            3 => $looseFruitReady && $this->weightsBalanced,
        ];
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
        return $this->bunchWeight !== null ? $this->bunchWeight - $this->distributedKilos : null;
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

        $netWeight = $this->number($this->netWeight);
        if ($netWeight === null || $netWeight <= 0) {
            $problems[] = 'Escriba cuánto pesó su viaje neto.';
        }

        $looseFruit = $this->number($this->looseFruitKilos) ?? 0.0;
        if ($looseFruit < 0) {
            $problems[] = 'Los kilos de fruta suelta no pueden ser negativos.';
        } elseif ($netWeight !== null && $netWeight > 0 && $looseFruit >= $netWeight) {
            $problems[] = 'La fruta suelta no puede pesar lo mismo o más que todo el viaje.';
        }

        $impurity = $this->number($this->impurityPercent) ?? 0.0;
        if ($impurity < 0 || $impurity > 100) {
            $problems[] = 'El porcentaje de impureza debe estar entre 0 y 100.';
        }

        foreach (self::BUNCH_FIELDS as $field) {
            if (($this->number($this->{$field}) ?? 0.0) < 0) {
                $problems[] = 'Los kilos no pueden ser negativos.';
                break;
            }
        }

        $longStalks = $this->number($this->longStalkCount) ?? 0.0;
        if ($longStalks < 0 || floor($longStalks) !== $longStalks) {
            $problems[] = 'Los racimos con tallo largo se cuentan enteros: 0, 1, 2…';
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
        $fruitTea = $this->result->tea + $this->result->industrialFactor;

        return match (true) {
            $fruitTea >= $levels['excellent'] => ['level' => 'excellent', 'label' => 'Excelente', 'message' => 'Su fruta llegó en muy buen estado. ¡Siga así!'],
            $fruitTea >= $levels['good'] => ['level' => 'good', 'label' => 'Buena', 'message' => 'Va bien. Con unos ajustes puede ganar más.'],
            default => ['level' => 'improve', 'label' => 'Puede mejorar', 'message' => 'Hay plata quedándose en el camino. Mire los consejos.'],
        };
    }

    #[Computed]
    public function weightsBalanced(): bool
    {
        return $this->result !== null && $this->result->weightsAreBalanced($this->bunchWeight);
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
        $looseFruitPercent = $this->looseFruitShare * 100;
        $impurity = $this->number($this->impurityPercent) ?? 0.0;
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

        if ($looseFruitPercent > 0 && $impurity > 0) {
            $tips[] = $this->tip('clean', 'Envíe la fruta suelta limpia', 'Sin hojas, palos ni tierra. La impureza le resta valor a cada kilo de fruta suelta.',
                ['looseFruitQuality' => 1.0]);
        }

        if ($looseFruitPercent < 98) {
            $tips[] = $this->tip('pick', 'Recoja la fruta suelta del suelo', 'Cada pepa cuenta: por cada 1 % más de fruta suelta en el viaje, su TEA sube 0,4 puntos. Recójala antes de 3 días.',
                ['looseFruitShare' => ($looseFruitPercent + 1) / 100], 'Por cada 1 % más');
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
            'Peso neto del viaje: '.number_format($this->number($this->netWeight), 0, ',', '.').' kg',
            'Rendimiento (TEA): '.number_format($this->result->tea * 100, 2, ',', '.').' %'.($this->quality ? ' — '.$this->quality['label'] : ''),
        ];

        if ($this->result->tripValue !== null) {
            $priceLine = 'Precio del aceite usado: $ '.number_format($this->oilPrice['price'], 0, ',', '.').' la tonelada';
            if ($this->oilPrice['updatedAt'] !== null) {
                $priceLine .= ' (publicado el '.$this->oilPrice['updatedAt']->timezone(config('app.timezone'))->format('d/m/Y').')';
            }

            $lines[] = $priceLine;
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
            'bunchWeight' => $this->bunchWeight,
            'ripe' => $this->number($this->ripe) ?? 0.0,
            'overripe' => $this->number($this->overripe) ?? 0.0,
            'unripe' => $this->number($this->unripe) ?? 0.0,
            'abnormal' => $this->number($this->abnormal) ?? 0.0,
            'rotten' => $this->number($this->rotten) ?? 0.0,
            'looseFruitShare' => $this->looseFruitShare,
            'looseFruitQuality' => (100 - ($this->number($this->impurityPercent) ?? 0.0)) / 100,
            // El FACIND es de la planta: sale solo de la configuración, el proveedor no lo ve ni lo cambia.
            'industrialFactor' => (float) config('palmcore.r100.industrial_factor'),
            'longStalkCount' => (int) ($this->number($this->longStalkCount) ?? 0),
            'oilPricePerTon' => $this->oilPrice['price'] ?? null,
        ];
    }

    private function number(string $value): ?float
    {
        return ColombianNumber::parse($value);
    }
}
