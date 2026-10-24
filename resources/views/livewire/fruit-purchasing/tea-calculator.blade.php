@php
    $pct = fn (float $value, int $decimals = 2): string => number_format($value * 100, $decimals, ',', '.').' %';
    $money = fn (float $value): string => '$ '.number_format($value, 0, ',', '.');
    $kilos = fn (float $value): string => number_format($value, 0, ',', '.').' kg';

    $bunchFields = [
        ['model' => 'ripe', 'code' => 'RM', 'label' => 'Racimo Maduro', 'value' => $this->bunchValues['ripe']],
        ['model' => 'overripe', 'code' => 'RSM', 'label' => 'Racimo Sobre Maduro', 'value' => $this->bunchValues['overripe']],
        ['model' => 'unripe', 'code' => 'RVM', 'label' => 'Racimo Verde Masudo', 'value' => $this->bunchValues['unripe']],
        ['model' => 'abnormal', 'code' => 'RVA', 'label' => 'Racimo Verde Anormal', 'value' => $this->bunchValues['abnormal']],
        ['model' => 'rotten', 'code' => 'RP', 'label' => 'Racimo Podrido', 'value' => $this->bunchValues['rotten']],
    ];

    $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-base tabular-nums text-gray-900 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20';
    $result = $this->result;
@endphp

<div class="mx-auto max-w-5xl px-4 py-6 sm:py-10">

    {{-- Encabezado --}}
    <header class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-brand-700">Fronda · Método R-100</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900 sm:text-3xl">¿Cuánto vale su fruta?</h1>
        <p class="mt-1 text-sm text-gray-600">Digite la calificación de su viaje en tolva y vea la TEA a pagar y lo que recibiría.</p>
    </header>

    {{-- Precio de compra --}}
    <section class="mb-6 rounded-2xl border border-brand-200 bg-brand-50 px-5 py-4">
        <label for="oilPricePerTon" class="block text-sm font-semibold text-brand-900">Precio de compra de la tonelada de aceite (CPO)</label>
        <div class="mt-2 flex items-center gap-2">
            <span class="text-lg font-semibold text-brand-900">$</span>
            <input id="oilPricePerTon" type="number" inputmode="decimal" min="0" step="any" placeholder="Ej. 4000000"
                   wire:model.live.debounce.400ms="oilPricePerTon" class="{{ $input }} max-w-xs text-lg font-semibold">
        </div>
        <p class="mt-1 text-xs text-brand-800">La tonelada de su fruta se paga a TEA × este precio.</p>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">

        {{-- Datos del viaje --}}
        <div class="space-y-6">
            <section class="rounded-2xl border border-gray-200 bg-white px-5 py-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Racimos</h2>

                <div class="mt-4 grid items-center gap-x-4 gap-y-1 sm:grid-cols-[1fr_11rem]">
                    <label for="bunchWeight" class="text-sm font-medium text-gray-800">Total peso de racimos (TPR) <span class="text-gray-500">kg</span></label>
                    <input id="bunchWeight" type="number" inputmode="decimal" min="0" step="any"
                           wire:model.live.debounce.400ms="bunchWeight" class="{{ $input }} font-semibold">
                </div>

                <div class="mt-5 space-y-3 border-t border-gray-100 pt-4">
                    @foreach ($bunchFields as $field)
                        <div wire:key="bunch-{{ $field['model'] }}" class="grid items-center gap-x-4 gap-y-1 sm:grid-cols-[1fr_11rem]">
                            <label for="{{ $field['model'] }}" class="text-sm text-gray-800">
                                Peso {{ $field['label'] }} ({{ $field['code'] }}) <span class="text-gray-500">kg</span>
                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ $pct($field['value'], 1) }}</span>
                            </label>
                            <input id="{{ $field['model'] }}" type="number" inputmode="decimal" min="0" step="any"
                                   wire:model.live.debounce.400ms="{{ $field['model'] }}" class="{{ $input }}">
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white px-5 py-5 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900">Fruta suelta e industria</h2>

                <div class="mt-4 space-y-3">
                    <div class="grid items-center gap-x-4 gap-y-1 sm:grid-cols-[1fr_11rem]">
                        <label for="looseFruitPercent" class="text-sm text-gray-800">% de Fruta Suelta (FS) <span class="text-gray-500">del viaje</span></label>
                        <input id="looseFruitPercent" type="number" inputmode="decimal" min="0" max="99" step="any"
                               wire:model.live.debounce.400ms="looseFruitPercent" class="{{ $input }}">
                    </div>
                    <div class="grid items-center gap-x-4 gap-y-1 sm:grid-cols-[1fr_11rem]">
                        <label for="looseFruitQualityPercent" class="text-sm text-gray-800">Calidad de Fruta Suelta (CFS) <span class="text-gray-500">% = 100 − impurezas</span></label>
                        <input id="looseFruitQualityPercent" type="number" inputmode="decimal" min="0" max="100" step="any"
                               wire:model.live.debounce.400ms="looseFruitQualityPercent" class="{{ $input }}">
                    </div>
                    <div class="grid items-center gap-x-4 gap-y-1 sm:grid-cols-[1fr_11rem]">
                        <label for="industrialFactorPercent" class="text-sm text-gray-800">FACIND <span class="text-gray-500">% (3,4 % + 1,3 %)</span></label>
                        <input id="industrialFactorPercent" type="number" inputmode="decimal" step="any"
                               wire:model.live.debounce.400ms="industrialFactorPercent" class="{{ $input }}">
                    </div>
                    <div class="grid items-center gap-x-4 gap-y-1 sm:grid-cols-[1fr_11rem]">
                        <label for="longStalkCount" class="text-sm text-gray-800">Cantidad de Pedúnculo Largo (CPL) <span class="text-gray-500">racimos</span></label>
                        <input id="longStalkCount" type="number" inputmode="numeric" min="0" step="1"
                               wire:model.live.debounce.400ms="longStalkCount" class="{{ $input }}">
                    </div>
                </div>
            </section>
        </div>

        {{-- Resultado --}}
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <section class="rounded-2xl bg-petrol-800 px-5 py-5 text-white shadow-sm" aria-live="polite">
                <p class="text-sm font-medium text-white/70">TEA a pagar</p>
                <p class="mt-1 text-4xl font-bold tabular-nums" data-testid="tea">{{ $result ? $pct($result->tea) : '—' }}</p>

                @if ($result)
                    <dl class="mt-4 space-y-1.5 border-t border-white/15 pt-3 text-sm tabular-nums">
                        <div class="flex justify-between"><dt class="text-white/70">Racimos</dt><dd>+ {{ $pct($result->bunchContribution) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-white/70">Fruta suelta</dt><dd>+ {{ $pct($result->looseFruitContribution) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-white/70">FACIND</dt><dd>− {{ $pct($result->industrialFactor) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-white/70">Pedúnculo largo</dt><dd>− {{ $pct($result->longStalkPenalty) }}</dd></div>
                    </dl>

                    <dl class="mt-4 space-y-3 border-t border-white/15 pt-4">
                        <div>
                            <dt class="text-sm text-white/70">Valor de su viaje</dt>
                            <dd class="text-2xl font-bold tabular-nums" data-testid="trip-value">{{ $result->tripValue !== null ? $money($result->tripValue) : 'Falta el precio' }}</dd>
                        </div>
                        <div class="flex justify-between text-sm tabular-nums">
                            <dt class="text-white/70">Precio por tonelada de su fruta</dt>
                            <dd>{{ $result->fruitPricePerTon !== null ? $money($result->fruitPricePerTon) : '—' }}</dd>
                        </div>
                        <div class="flex justify-between text-sm tabular-nums">
                            <dt class="text-white/70">Fruto total del viaje</dt>
                            <dd>{{ $kilos($result->totalFruitKilos) }}</dd>
                        </div>
                    </dl>
                @endif
            </section>

            @if ($result && ! $result->weightsAreBalanced((float) str_replace(',', '.', $bunchWeight)))
                <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
                    Los cinco tipos de racimo suman {{ $result->weightGap > 0 ? 'más' : 'menos' }} que el TPR
                    ({{ $kilos(abs($result->weightGap)) }} de diferencia). Revise los pesos: la TEA no es confiable así.
                </div>
            @endif

            @if (! $result && $this->problems !== [])
                <ul class="mt-4 space-y-1 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600">
                    @foreach ($this->problems as $problem)
                        <li wire:key="problem-{{ $loop->index }}">• {{ $problem }}</li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-4 text-xs leading-relaxed text-gray-500">
                TEA = (RM·21% + RSM·12,5% + RVM·11% + RVA·10% + RP·1,5%) / TPR + (%FS·40%·%CFS) − %FACIND − (NPL·1,5 / TPR).
                El valor es un estimado; el pago final sale de la calificación en planta.
            </p>
        </aside>
    </div>
</div>
