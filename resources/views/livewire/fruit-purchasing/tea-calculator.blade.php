@php
    $pct = fn (float $value, int $decimals = 2): string => number_format($value * 100, $decimals, ',', '.').' %';
    $money = fn (float $value): string => '$ '.number_format($value, 0, ',', '.');
    $kilos = fn (float $value): string => number_format($value, 0, ',', '.').' kg';

    $bunchCards = [
        'ripe' => ['name' => 'Maduro', 'code' => 'RM', 'photo' => 'maduro', 'tone' => 'good',
            'hint' => 'Rojo-naranja brillante, con 5 a 20 frutos caídos. Es el que más paga.'],
        'overripe' => ['name' => 'Sobremaduro', 'code' => 'RSM', 'photo' => 'sobremaduro', 'tone' => 'warn',
            'hint' => 'Más de la mitad de los frutos ya se cayó. Color oscuro, casi negro.'],
        'unripe' => ['name' => 'Verde masudo', 'code' => 'RVM', 'photo' => 'verde-masudo', 'tone' => 'warn',
            'hint' => 'Tiene color de maduro, pero ningún fruto se ha caído.'],
        'abnormal' => ['name' => 'Verde anormal', 'code' => 'RVA', 'photo' => 'verde-anormal', 'tone' => 'warn',
            'hint' => 'Mal formado: pocos frutos, colores raros, pequeño.'],
        'rotten' => ['name' => 'Podrido', 'code' => 'RP', 'photo' => 'podrido', 'tone' => 'bad',
            'hint' => 'Huele agrio y tiene hongo blanco. Casi no paga.'],
    ];
    $toneBadge = [
        'good' => 'bg-brand-100 text-brand-800',
        'warn' => 'bg-amber-100 text-amber-900',
        'bad' => 'bg-red-100 text-red-800',
    ];
    $tipIcons = ['water' => '💧', 'stick' => '🪵', 'stalk' => '✂️', 'clean' => '🧹', 'pick' => '🧺'];
    $qualityStyle = [
        'excellent' => ['icon' => '🌟', 'box' => 'bg-brand-500 text-white'],
        'good' => ['icon' => '👍', 'box' => 'bg-sky-400 text-petrol-900'],
        'improve' => ['icon' => '💡', 'box' => 'bg-amber-300 text-amber-950'],
    ];

    $field = 'block w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3 text-2xl font-semibold tabular-nums text-gray-900 placeholder:text-gray-400 placeholder:font-normal focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/20';
    $format = 'x-on:blur="$el.value = window.frondaFormatNumber($el.value); $el.dispatchEvent(new Event(\'input\'))"';

    $result = $this->result;
    $quality = $this->quality;
    $remaining = $this->remainingKilos;
    $bunchWeightValue = $remaining !== null ? $remaining + $this->distributedKilos : null;
    $progress = $bunchWeightValue ? min(100, max(0, $this->distributedKilos / $bunchWeightValue * 100)) : 0;
    $balanced = $this->weightsBalanced;
@endphp

<div class="mx-auto max-w-6xl px-4 pb-36 pt-6 text-lg text-gray-800 sm:pt-10 lg:pb-12">
    <style>
        @keyframes r100-pop { 0% { transform: scale(.96); opacity: .4 } 60% { transform: scale(1.03) } 100% { transform: scale(1); opacity: 1 } }
        .r100-pop { animation: r100-pop .35s ease-out }
    </style>

    {{-- Encabezado --}}
    <header class="mb-8 flex items-start gap-4">
        <img src="{{ asset('images/isotipo.png') }}" alt="" class="hidden h-16 w-16 shrink-0 sm:block">
        <div>
            <p class="text-base font-semibold text-brand-700">Fronda · Método R-100</p>
            <h1 class="mt-1 text-3xl font-extrabold leading-tight text-petrol-900 sm:text-5xl">¿Cuánto vale su fruta?</h1>
            <p class="mt-3 max-w-2xl text-xl text-gray-600">Llene los pasos con lo que le dieron en la báscula. El resultado aparece solo, sin oprimir ningún botón.</p>
        </div>
    </header>

    <div class="grid gap-8 lg:grid-cols-[1fr_25rem]">

        {{-- ═══ Pasos ═══ --}}
        <div class="space-y-8 lg:col-start-1">

            {{-- Precio --}}
            <section class="rounded-3xl border-2 border-brand-200 bg-brand-50 p-5 sm:p-7">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    <span class="text-3xl" aria-hidden="true">💲</span> ¿A cómo pagan la tonelada de aceite?
                </h2>
                <p class="mt-1 text-lg text-gray-700">Pregúntelo en la báscula o en la oficina de la extractora.</p>
                <label for="oilPricePerTon" class="sr-only">Precio de compra de la tonelada de aceite (CPO)</label>
                <div class="relative mt-4 max-w-md">
                    <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-2xl font-bold text-gray-500">$</span>
                    <input id="oilPricePerTon" type="text" inputmode="decimal" autocomplete="off" placeholder="4.000.000"
                           wire:model.live.debounce.500ms="oilPricePerTon" {!! $format !!} class="{{ $field }} pl-10">
                </div>
                <p class="mt-2 text-base text-gray-600">Precio de compra de la tonelada de aceite (CPO). Su fruta se paga a TEA × este precio.</p>
            </section>

            {{-- Paso 1 --}}
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-petrol-800 text-xl text-white">1</span>
                    ¿Cuánto pesaron los racimos?
                </h2>
                <p class="mt-2 text-lg text-gray-600">Solo los racimos, sin la fruta suelta. En la planta lo llaman <strong>TPR</strong> (total peso de racimos).</p>
                <label for="bunchWeight" class="sr-only">Total peso de racimos (TPR) en kilos</label>
                <div class="relative mt-4 max-w-md">
                    <input id="bunchWeight" type="text" inputmode="decimal" autocomplete="off" placeholder="18.000"
                           wire:model.live.debounce.500ms="bunchWeight" {!! $format !!} class="{{ $field }} pr-16">
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xl font-semibold text-gray-500">kg</span>
                </div>
            </section>

            {{-- Paso 2 --}}
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-petrol-800 text-xl text-white">2</span>
                    ¿Cómo venían los racimos?
                </h2>
                <p class="mt-2 text-lg text-gray-600">Mire las fotos y escriba cuántos kilos había de cada tipo. Si no trajo de alguno, déjelo vacío.</p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ($bunchCards as $model => $card)
                        <div wire:key="bunch-{{ $model }}" class="overflow-hidden rounded-2xl border-2 border-gray-200 bg-white focus-within:border-brand-500">
                            <img src="{{ asset('images/r100/'.$card['photo'].'.jpg') }}" alt="Racimo {{ strtolower($card['name']) }}" loading="lazy" class="h-36 w-full object-cover">
                            <div class="p-4">
                                <label for="{{ $model }}" class="block text-xl font-bold text-petrol-900">{{ $card['name'] }} <span class="text-base font-normal text-gray-500">({{ $card['code'] }})</span></label>
                                <span class="mt-1 inline-block rounded-full px-3 py-1 text-sm font-bold {{ $toneBadge[$card['tone']] }}">Paga {{ str_replace(',0 %', ' %', $pct($this->bunchValues[$model], 1)) }}</span>
                                <p class="mt-1 text-base leading-snug text-gray-600">{{ $card['hint'] }}</p>
                                <div class="relative mt-3">
                                    <input id="{{ $model }}" type="text" inputmode="decimal" autocomplete="off" placeholder="0"
                                           aria-label="Peso Racimo {{ $card['name'] }} ({{ $card['code'] }}) en kilos"
                                           wire:model.live.debounce.500ms="{{ $model }}" {!! $format !!} class="{{ $field }} pr-14">
                                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-lg font-semibold text-gray-500">kg</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Cuánto falta por repartir --}}
                @if ($remaining !== null)
                    <div class="mt-6 rounded-2xl bg-gray-50 p-4 sm:p-5" aria-live="polite">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 text-lg">
                            <span>Ha repartido <strong class="tabular-nums">{{ $kilos($this->distributedKilos) }}</strong> de <strong class="tabular-nums">{{ $kilos($bunchWeightValue) }}</strong></span>
                            @if ($balanced)
                                <span class="font-bold text-brand-700">✔ Todo cuadra</span>
                            @elseif ($remaining > 0)
                                <span class="font-bold text-amber-700">Faltan {{ $kilos($remaining) }}</span>
                            @else
                                <span class="font-bold text-red-700">Sobran {{ $kilos(abs($remaining)) }}</span>
                            @endif
                        </div>
                        <div class="mt-3 h-4 overflow-hidden rounded-full bg-gray-200">
                            <div class="h-full rounded-full transition-all duration-500 {{ $balanced ? 'bg-brand-500' : ($remaining < 0 ? 'bg-red-500' : 'bg-amber-400') }}" style="width: {{ $progress }}%"></div>
                        </div>
                        @if (! $balanced && $remaining > 0)
                            <button type="button" wire:click="fillRipeWithRemainder"
                                    class="mt-4 w-full rounded-xl border-2 border-brand-600 bg-white px-4 py-3 text-lg font-bold text-brand-700 hover:bg-brand-50 sm:w-auto">
                                Poner los {{ $kilos($remaining) }} que faltan como maduro
                            </button>
                        @elseif ($remaining < 0 && ! $balanced)
                            <p class="mt-3 text-base text-red-700">Los tipos suman más que el peso de los racimos. Revise los kilos de cada tipo.</p>
                        @endif
                    </div>
                @endif
            </section>

            {{-- Paso 3 --}}
            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-petrol-800 text-xl text-white">3</span>
                    ¿Trajo fruta suelta?
                </h2>

                <div class="mt-4 grid gap-5 sm:grid-cols-[12rem_1fr] sm:items-start">
                    <img src="{{ asset('images/r100/fruta-suelta.jpg') }}" alt="Fruta suelta" loading="lazy" class="h-32 w-full rounded-2xl object-cover sm:h-full">
                    <div>
                        <label for="looseFruitPercent" class="block text-xl font-bold text-petrol-900">De cada 100 kilos del viaje, ¿cuántos eran fruta suelta?</label>
                        <p class="text-base text-gray-600">La pepa que llega suelta, sin racimo. Paga muy bien: 0,4 puntos por cada 1 %. <span class="text-gray-500">(% de Fruta Suelta, FS)</span></p>
                        <div class="relative mt-3 max-w-[12rem]">
                            <input id="looseFruitPercent" type="text" inputmode="decimal" autocomplete="off" placeholder="0"
                                   wire:model.live.debounce.500ms="looseFruitPercent" {!! $format !!} class="{{ $field }} pr-12">
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xl font-semibold text-gray-500">%</span>
                        </div>
                    </div>
                </div>

                <fieldset class="mt-6">
                    <legend class="text-xl font-bold text-petrol-900">¿Qué tan limpia venía la fruta suelta?</legend>
                    <p class="text-base text-gray-600">Calidad de Fruta Suelta (CFS): 100 % menos la basura.</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        @foreach (\App\Livewire\FruitPurchasing\TeaCalculator::LOOSE_FRUIT_QUALITY_OPTIONS as $percent => $label)
                            <button type="button" wire:key="quality-{{ $percent }}" wire:click="chooseLooseFruitQuality('{{ $percent }}')"
                                    aria-pressed="{{ $looseFruitQualityPercent === (string) $percent ? 'true' : 'false' }}"
                                    @class([
                                        'rounded-xl border-2 px-4 py-3 text-left text-lg font-semibold transition',
                                        'border-brand-600 bg-brand-50 text-brand-800 ring-4 ring-brand-600/15' => $looseFruitQualityPercent === (string) $percent,
                                        'border-gray-200 bg-white text-gray-700 hover:border-gray-300' => $looseFruitQualityPercent !== (string) $percent,
                                    ])>
                                {{ $label }}
                                <span class="block text-base font-normal text-gray-500">{{ $percent }} %</span>
                            </button>
                        @endforeach
                    </div>
                </fieldset>

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <label for="longStalkCount" class="block text-xl font-bold text-petrol-900">¿Cuántos racimos llegaron con el tallo largo?</label>
                    <p class="text-base text-gray-600">El tallo (pedúnculo) largo descuenta 1,5 kilos por racimo. <span class="text-gray-500">(Cantidad de Pedúnculo Largo, CPL)</span></p>
                    <div class="mt-3 max-w-[12rem]">
                        <input id="longStalkCount" type="text" inputmode="numeric" autocomplete="off" placeholder="0"
                               wire:model.live.debounce.500ms="longStalkCount" class="{{ $field }}">
                    </div>
                </div>

                <details class="mt-6 rounded-2xl bg-gray-50 px-4 py-3">
                    <summary class="cursor-pointer text-base font-semibold text-gray-600">Datos de la planta (no necesita cambiarlos)</summary>
                    <label for="industrialFactorPercent" class="mt-3 block text-lg font-semibold text-gray-800">FACIND: costo de procesar la fruta</label>
                    <p class="text-base text-gray-600">3,4 % de proceso + 1,3 % de pérdidas permitidas.</p>
                    <div class="relative mt-2 max-w-[12rem]">
                        <input id="industrialFactorPercent" type="text" inputmode="decimal" autocomplete="off"
                               wire:model.live.debounce.500ms="industrialFactorPercent" class="{{ $field }} pr-12">
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xl font-semibold text-gray-500">%</span>
                    </div>
                </details>
            </section>
        </div>

        {{-- ═══ Resultado ═══ --}}
        <aside id="resultado" class="scroll-mt-6 lg:sticky lg:top-6 lg:col-start-2 lg:row-span-2 lg:row-start-1 lg:self-start">
            <section class="overflow-hidden rounded-3xl bg-petrol-800 text-white shadow-lg" aria-live="polite">
                @if ($result && $balanced)
                    @if ($quality)
                        <div wire:key="quality-{{ $quality['level'] }}" class="r100-pop flex items-center gap-3 px-6 py-4 {{ $qualityStyle[$quality['level']]['box'] }}">
                            <span class="text-4xl" aria-hidden="true">{{ $qualityStyle[$quality['level']]['icon'] }}</span>
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wide opacity-80">Calidad de su fruta</p>
                                <p class="text-2xl font-extrabold">{{ $quality['label'] }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="p-6">
                        <p class="text-lg text-white/75">{{ $result->tripValue !== null ? 'Usted recibiría' : 'Su fruta rindió' }}</p>
                        @if ($result->tripValue !== null)
                            <p wire:key="value-{{ round($result->tripValue) }}" class="r100-pop mt-1 text-4xl font-extrabold tabular-nums sm:text-5xl" data-testid="trip-value">{{ $money($result->tripValue) }}</p>
                            <p class="mt-3 text-xl">Su fruta rindió <strong class="tabular-nums" data-testid="tea">{{ $pct($result->tea) }}</strong> <span class="text-base text-white/60">(TEA)</span></p>
                        @else
                            <p wire:key="tea-{{ round($result->tea * 10000) }}" class="r100-pop mt-1 text-5xl font-extrabold tabular-nums" data-testid="tea">{{ $pct($result->tea) }}</p>
                            <p class="mt-3 text-lg text-amber-200">Escriba arriba el precio de la tonelada para ver cuánta plata es.</p>
                        @endif

                        @if ($quality)
                            <p class="mt-2 text-lg text-white/80">{{ $quality['message'] }}</p>
                        @endif

                        <dl class="mt-5 space-y-2 border-t border-white/15 pt-4 text-lg tabular-nums">
                            @if ($result->fruitPricePerTon !== null)
                                <div class="flex justify-between gap-3"><dt class="text-white/70">Le pagan cada tonelada a</dt><dd class="font-semibold">{{ $money($result->fruitPricePerTon) }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="text-white/70">Peso de todo el viaje</dt><dd class="font-semibold">{{ $kilos($result->totalFruitKilos) }}</dd></div>
                        </dl>

                        <details class="mt-4 rounded-2xl bg-white/5 px-4 py-3">
                            <summary class="cursor-pointer text-base font-semibold text-white/80">¿De dónde sale este número?</summary>
                            <dl class="mt-3 space-y-1.5 text-base tabular-nums">
                                <div class="flex justify-between"><dt class="text-white/70">Sus racimos aportan</dt><dd>+ {{ $pct($result->bunchContribution) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-white/70">La fruta suelta suma</dt><dd>+ {{ $pct($result->looseFruitContribution) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-white/70">Costo de la planta (FACIND)</dt><dd>− {{ $pct($result->industrialFactor) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-white/70">Tallos largos</dt><dd>− {{ $pct($result->longStalkPenalty) }}</dd></div>
                                <div class="flex justify-between border-t border-white/15 pt-1.5 font-bold"><dt>TEA a pagar</dt><dd>{{ $pct($result->tea) }}</dd></div>
                            </dl>
                        </details>

                        @if ($this->whatsappUrl)
                            <a href="{{ $this->whatsappUrl }}" target="_blank" rel="noopener"
                               class="mt-5 flex w-full items-center justify-center gap-3 rounded-2xl bg-[#25D366] px-5 py-4 text-xl font-bold text-petrol-900 hover:brightness-95">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.3-.3-.3-.5-.4Z"/></svg>
                                Enviar por WhatsApp
                            </a>
                        @endif
                        <button type="button" wire:click="startOver" class="mt-3 w-full rounded-2xl border-2 border-white/25 px-5 py-3 text-lg font-semibold text-white/90 hover:bg-white/10">
                            Calcular otro viaje
                        </button>
                    </div>
                @else
                    <div class="p-6 text-center">
                        <p class="text-5xl" aria-hidden="true">🌴</p>
                        <p class="mt-3 text-2xl font-bold">Aquí verá cuánto vale su fruta</p>
                        <p class="mt-2 text-lg text-white/75" data-testid="tea">
                            @if ($result && ! $balanced)
                                Primero haga cuadrar los kilos de los racimos en el paso 2.
                            @elseif ($this->problems !== [])
                                {{ $this->problems[0] }}
                            @endif
                        </p>
                    </div>
                @endif
            </section>

            <p class="mt-4 px-2 text-sm leading-relaxed text-gray-500">
                Fórmula R-100: TEA = (RM·21% + RSM·12,5% + RVM·11% + RVA·10% + RP·1,5%) / TPR + (%FS·40%·%CFS) − %FACIND − (NPL·1,5 / TPR).
                Es un estimado; el pago final sale de la calificación en planta.
            </p>
        </aside>

        {{-- ═══ Consejos ═══ --}}
        @if ($this->tips !== [])
            <section class="rounded-3xl border-2 border-amber-200 bg-amber-50 p-5 sm:p-7 lg:col-start-1">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900"><span class="text-3xl" aria-hidden="true">💡</span> Cómo ganar más en el próximo viaje</h2>
                <div class="mt-5 space-y-4">
                    @foreach ($this->tips as $tip)
                        <div wire:key="tip-{{ $tip['key'] }}" class="flex gap-4 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                            <span class="text-4xl leading-none" aria-hidden="true">{{ $tipIcons[$tip['key']] }}</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-xl font-bold text-petrol-900">{{ $tip['title'] }}</h3>
                                <p class="mt-1 text-lg leading-snug text-gray-700">{{ $tip['text'] }}</p>
                                @if ($tip['moneyGain'] !== null && $tip['moneyGain'] > 0)
                                    <p class="mt-2 inline-block rounded-full bg-brand-100 px-3 py-1 text-lg font-bold text-brand-800">{{ $tip['gainLabel'] }}: + {{ $money($tip['moneyGain']) }}</p>
                                @elseif ($tip['teaGain'] !== null && $tip['teaGain'] > 0)
                                    <p class="mt-2 inline-block rounded-full bg-brand-100 px-3 py-1 text-lg font-bold text-brand-800">{{ $tip['gainLabel'] }}: + {{ number_format($tip['teaGain'] * 100, 2, ',', '.') }} puntos de TEA</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- Barra fija en el celular: el resultado siempre a la vista --}}
    <a href="#resultado" class="fixed inset-x-0 bottom-0 z-20 flex items-center justify-between gap-3 border-t-4 border-brand-500 bg-petrol-800 px-5 py-4 text-white shadow-2xl lg:hidden">
        @if ($result && $balanced)
            <span class="text-base text-white/75">{{ $result->tripValue !== null ? 'Usted recibiría' : 'Su fruta rindió' }}</span>
            <span class="text-2xl font-extrabold tabular-nums">{{ $result->tripValue !== null ? $money($result->tripValue) : $pct($result->tea) }}</span>
        @else
            <span class="text-lg font-semibold">Llene los pasos para ver su resultado</span>
            <span class="text-2xl" aria-hidden="true">↓</span>
        @endif
    </a>
</div>
