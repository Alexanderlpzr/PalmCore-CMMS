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
    $example = 'mt-2 text-base text-gray-500';

    $steps = $this->completedSteps;
    $sectionBox = fn (int $step): string => 'rounded-3xl border-2 bg-white p-5 shadow-sm transition-colors sm:p-7 '.($steps[$step] ? 'border-brand-300' : 'border-gray-200');
    $stepBadge = fn (int $step): \Illuminate\Support\HtmlString => new \Illuminate\Support\HtmlString($steps[$step]
        ? '<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-600 text-white" data-step-done="'.$step.'"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><span class="sr-only">Paso '.$step.' listo</span></span>'
        : '<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-petrol-800 text-xl text-white">'.$step.'</span>');

    $oilPrice = $this->oilPrice;
    $result = $this->result;
    $quality = $this->quality;
    $bunchWeight = $this->bunchWeight;
    $remaining = $this->remainingKilos;
    $progress = $bunchWeight ? min(100, max(0, $this->distributedKilos / $bunchWeight * 100)) : 0;
    $balanced = $this->weightsBalanced;
@endphp

<div class="mx-auto max-w-6xl px-4 pb-36 pt-6 text-lg text-gray-800 sm:pt-10 lg:pb-12">
    <style>
        @keyframes r100-pop { 0% { transform: scale(.96); opacity: .4 } 60% { transform: scale(1.03) } 100% { transform: scale(1); opacity: 1 } }
        .r100-pop { animation: r100-pop .35s ease-out }
        [x-cloak] { display: none !important }
    </style>

    {{-- Encabezado --}}
    <header class="mb-8">
        <div class="flex items-center gap-4 sm:gap-6">
            <img src="{{ asset('images/logo.png') }}" alt="Extractora El Pajuil" width="512" height="512"
                 class="h-20 w-20 shrink-0 drop-shadow-md sm:h-32 sm:w-32">
            <div>
                <h1 class="text-4xl font-black leading-none tracking-tight text-petrol-900 sm:text-7xl">Método <span class="text-brand-600">R-100</span></h1>
                <p class="mt-2 text-xl font-bold text-petrol-800 sm:mt-3 sm:text-3xl">¿Cuánto vale su fruta?</p>
            </div>
        </div>
        <p class="mt-4 max-w-2xl text-xl text-gray-600">Llene los pasos con lo que le dieron en la báscula. El resultado aparece solo, sin oprimir ningún botón.</p>
    </header>

    <div class="grid gap-8 lg:grid-cols-[1fr_25rem]">

        {{-- ═══ Pasos ═══ --}}
        <div class="space-y-8 lg:col-start-1">

            {{-- Precio --}}
            <section class="rounded-3xl border-2 border-brand-200 bg-brand-50 p-5 sm:p-7">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    <span class="text-3xl" aria-hidden="true">💲</span> ¿Cuánto cuesta la tonelada de aceite (CPO) a precio (FEP)?
                </h2>
                <p class="mt-1 text-lg text-gray-700">Pregúntelo en la báscula o en la oficina de la extractora.</p>
                <label for="oilPricePerTon" class="sr-only">Precio de compra de la tonelada de aceite (CPO)</label>
                <div class="relative mt-4 max-w-md">
                    <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-2xl font-bold text-gray-500">$</span>
                    <input id="oilPricePerTon" type="text" inputmode="decimal" autocomplete="off" aria-describedby="oilPricePerTon-example"
                           wire:model.live.debounce.500ms="oilPricePerTon" {!! $format !!} class="{{ $field }} pl-10">
                </div>
                @if ($this->oilPriceLooksPerKilo)
                    <p class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-base font-semibold text-amber-900" role="alert">Ese precio parece ser el del kilo. Aquí va el de la tonelada (1.000 kilos).</p>
                @endif
                <p id="oilPricePerTon-example" class="{{ $example }}">Ejemplo: 4.000.000</p>
                @if ($oilPrice && $oilPrice['updatedAt'])
                    <p class="mt-1 text-base text-gray-600">
                        Precio publicado por la extractora el {{ $oilPrice['updatedAt']->timezone(config('app.timezone'))->format('d/m/Y') }}: <strong class="tabular-nums">{{ $money($oilPrice['price']) }}</strong>
                    </p>
                @endif
                <p class="mt-2 text-base text-gray-600">Precio de compra de la tonelada de aceite (CPO). Su fruta se paga a TEA × este precio.</p>
            </section>

            {{-- Paso 1: peso neto --}}
            <section class="{{ $sectionBox(1) }}">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    {{ $stepBadge(1) }}
                    <label for="netWeight">¿Cuánto pesó su viaje NETO?</label>
                </h2>
                <p class="mt-2 text-lg text-gray-600">Fruta suelta y peso de racimos, todo junto. Se lo dan en la báscula.</p>
                <div class="relative mt-4 max-w-md">
                    <input id="netWeight" type="text" inputmode="decimal" autocomplete="off" aria-describedby="netWeight-example"
                           wire:model.live.debounce.500ms="netWeight" {!! $format !!} class="{{ $field }} pr-16">
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xl font-semibold text-gray-500">kg</span>
                </div>
                <p id="netWeight-example" class="{{ $example }}">Ejemplo: 20.000 kg</p>
            </section>

            {{-- Paso 2: fruta suelta --}}
            <section class="{{ $sectionBox(2) }}">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    {{ $stepBadge(2) }}
                    ¿Trajo fruta suelta?
                </h2>

                <div class="mt-4 grid gap-5 sm:grid-cols-[12rem_1fr] sm:items-start">
                    <img src="{{ asset('images/r100/fruta-suelta.jpg') }}" alt="Fruta suelta" loading="lazy" class="h-32 w-full rounded-2xl object-cover sm:h-44">
                    <div class="space-y-5">
                        <div>
                            <label for="looseFruitKilos" class="block text-xl font-bold text-petrol-900">¿Cuántos kilos de fruta suelta?</label>
                            <p class="text-base text-gray-600">La fruta que llega suelta, sin racimo. Se lo dan en la báscula.</p>
                            <div class="relative mt-3 max-w-xs">
                                <input id="looseFruitKilos" type="text" inputmode="decimal" autocomplete="off" aria-describedby="looseFruitKilos-example"
                                       wire:model.live.debounce.500ms="looseFruitKilos" {!! $format !!} class="{{ $field }} pr-14">
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xl font-semibold text-gray-500">kg</span>
                            </div>
                            <p id="looseFruitKilos-example" class="{{ $example }}">Ejemplo: 1.600 kg. Si no trajo, déjelo vacío.</p>
                        </div>
                        <div>
                            <label for="impurityPercent" class="block text-xl font-bold text-petrol-900">Escriba el porcentaje de impureza</label>
                            <p class="text-base text-gray-600">Hojas, palos o tierra en la fruta suelta. Se lo dan en la tolva.</p>
                            <div class="relative mt-3 max-w-[12rem]">
                                <input id="impurityPercent" type="text" inputmode="decimal" autocomplete="off" aria-describedby="impurityPercent-example"
                                       wire:model.live.debounce.500ms="impurityPercent" {!! $format !!} class="{{ $field }} pr-12">
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xl font-semibold text-gray-500">%</span>
                            </div>
                            <p id="impurityPercent-example" class="{{ $example }}">Ejemplo: 5 %. Si la fruta llegó limpia, déjelo vacío.</p>
                        </div>
                    </div>
                </div>

                @if ($bunchWeight !== null && $this->looseFruitShare !== null)
                    <div class="mt-6 grid gap-3 rounded-2xl bg-gray-50 p-4 text-lg sm:grid-cols-2 sm:p-5" aria-live="polite">
                        <p>Peso de los racimos <span class="text-gray-500">(TPR)</span>: <strong class="tabular-nums">{{ $kilos($bunchWeight) }}</strong></p>
                        <p>La fruta suelta es el <strong class="tabular-nums">{{ $pct($this->looseFruitShare, 1) }}</strong> del viaje <span class="text-gray-500">(FS)</span></p>
                    </div>
                @endif
            </section>

            {{-- Paso 3: racimos --}}
            <section class="{{ $sectionBox(3) }}" x-data="{ photo: null, name: '' }" x-on:keydown.escape.window="photo = null">
                <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                    {{ $stepBadge(3) }}
                    ¿Cómo venían los racimos?
                </h2>
                <p class="mt-2 text-lg text-gray-600">Escriba cuántos kilos había de cada tipo. Si no trajo de alguno, déjelo vacío. Toque la foto para verla grande.</p>

                <div class="mt-6 space-y-3">
                    @foreach ($bunchCards as $model => $card)
                        <div wire:key="bunch-{{ $model }}" data-bunch-row="{{ $model }}"
                             class="grid grid-cols-[5rem_1fr] items-start gap-x-4 gap-y-3 rounded-2xl border-2 border-gray-200 bg-white p-3 focus-within:border-brand-500 sm:grid-cols-[6rem_1fr_12rem] sm:items-center sm:p-4">
                            <button type="button" class="group relative overflow-hidden rounded-xl focus:outline-none focus:ring-4 focus:ring-brand-600/30"
                                    x-on:click="photo = @js(asset('images/r100/'.$card['photo'].'.jpg')); name = @js($card['name'])"
                                    aria-label="Ver grande la foto del racimo {{ strtolower($card['name']) }}">
                                <img src="{{ asset('images/r100/'.$card['photo'].'.jpg') }}" alt="Racimo {{ strtolower($card['name']) }}" loading="lazy"
                                     class="h-20 w-20 object-cover transition group-hover:scale-105 sm:h-24 sm:w-24">
                                <span class="absolute bottom-1 right-1 flex h-7 w-7 items-center justify-center rounded-full bg-black/60 text-white" aria-hidden="true">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5M11 8v6M8 11h6"/></svg>
                                </span>
                            </button>
                            <div class="min-w-0">
                                <label for="{{ $model }}" class="text-xl font-bold text-petrol-900">{{ $card['name'] }} <span class="text-base font-normal text-gray-500">({{ $card['code'] }})</span></label>
                                <span class="ml-1 inline-block rounded-full px-2.5 py-0.5 align-middle text-sm font-bold {{ $toneBadge[$card['tone']] }}">Paga {{ str_replace(',0 %', ' %', $pct($this->bunchValues[$model], 1)) }}</span>
                                <p class="mt-1 text-base leading-snug text-gray-600">{{ $card['hint'] }}</p>
                            </div>
                            <div class="relative col-span-2 sm:col-span-1">
                                <input id="{{ $model }}" type="text" inputmode="decimal" autocomplete="off"
                                       aria-label="Peso Racimo {{ $card['name'] }} ({{ $card['code'] }}) en kilos"
                                       wire:model.live.debounce.500ms="{{ $model }}" {!! $format !!} class="{{ $field }} pr-14">
                                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-lg font-semibold text-gray-500">kg</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Foto grande del racimo --}}
                <div x-show="photo" x-cloak x-transition.opacity x-on:click="photo = null" role="dialog" aria-modal="true" aria-label="Foto del racimo"
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4">
                    <figure class="w-full max-w-xl">
                        <img :src="photo" :alt="'Racimo ' + name" class="max-h-[70vh] w-full rounded-2xl object-contain">
                        <figcaption class="mt-4 text-center text-3xl font-bold text-white" x-text="name"></figcaption>
                        <p class="mt-2 text-center text-lg text-white/70">Toque en cualquier parte para cerrar</p>
                    </figure>
                </div>

                {{-- Cuánto falta por repartir --}}
                @if ($remaining !== null)
                    <div class="mt-6 rounded-2xl bg-gray-50 p-4 sm:p-5" aria-live="polite">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 text-lg">
                            <span>Ha repartido <strong class="tabular-nums">{{ $kilos($this->distributedKilos) }}</strong> de <strong class="tabular-nums">{{ $kilos($bunchWeight) }}</strong> de racimos</span>
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

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <label for="longStalkCount" class="block text-xl font-bold text-petrol-900">¿Cuántos racimos llegaron con el tallo largo?</label>
                    <p class="text-base text-gray-600">El tallo (pedúnculo) largo descuenta 1,5 kilos por racimo. <span class="text-gray-500">(Cantidad de Pedúnculo Largo, CPL)</span></p>
                    <div class="mt-3 max-w-[12rem]">
                        <input id="longStalkCount" type="text" inputmode="numeric" autocomplete="off" aria-describedby="longStalkCount-example"
                               wire:model.live.debounce.500ms="longStalkCount" class="{{ $field }}">
                    </div>
                    <p id="longStalkCount-example" class="{{ $example }}">Si ninguno llegó con el tallo largo, déjelo vacío.</p>
                </div>
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
                            <p class="mt-3 text-lg text-amber-200">Escriba arriba el precio de la tonelada de aceite para ver cuánta plata es.</p>
                        @endif

                        @if ($quality)
                            <p class="mt-2 text-lg text-white/80">{{ $quality['message'] }}</p>
                        @endif

                        <dl class="mt-5 space-y-2 border-t border-white/15 pt-4 text-lg tabular-nums">
                            @if ($result->fruitPricePerTon !== null)
                                <div class="flex justify-between gap-3"><dt class="text-white/70">Le pagan cada tonelada a</dt><dd class="font-semibold">{{ $money($result->fruitPricePerTon) }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-3"><dt class="text-white/70">Peso neto del viaje</dt><dd class="font-semibold">{{ $kilos($result->totalFruitKilos) }}</dd></div>
                        </dl>

                        <details class="mt-4 rounded-2xl bg-white/5 px-4 py-3">
                            <summary class="cursor-pointer text-base font-semibold text-white/80">¿De dónde sale este número?</summary>
                            <dl class="mt-3 space-y-1.5 text-base tabular-nums">
                                {{-- El FACIND no se muestra: va ya descontado en lo que aportan los racimos, para que la suma cuadre. --}}
                                <div class="flex justify-between"><dt class="text-white/70">Sus racimos aportan</dt><dd>+ {{ $pct($result->bunchContribution - $result->industrialFactor) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-white/70">La fruta suelta suma</dt><dd>+ {{ $pct($result->looseFruitContribution) }}</dd></div>
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
                                Primero haga cuadrar los kilos de los racimos en el paso 3.
                            @elseif ($this->problems !== [])
                                {{ $this->problems[0] }}
                            @endif
                        </p>
                    </div>
                @endif
            </section>

            <p class="mt-4 px-2 text-sm leading-relaxed text-gray-500">
                Fórmula R-100: TEA = (RM·21% + RSM·12,5% + RVM·11% + RVA·10% + RP·1,5%) / TPR + (%FS·40%·%CFS) − %FACIND − (NPL·1,5 / TPR)%.
                TPR = peso neto − fruta suelta; CFS = 100 % − impureza. Es un estimado; el pago final sale de la calificación en planta.
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
                                @php
                                    $chip = $tip['isLoss'] ? 'bg-red-50 text-red-800' : 'bg-brand-100 text-brand-800';
                                    $sign = $tip['isLoss'] ? '' : '+ ';
                                @endphp
                                @if ($tip['moneyGain'] !== null && $tip['moneyGain'] > 0)
                                    <p class="mt-2 inline-block rounded-full px-3 py-1 text-lg font-bold {{ $chip }}">{{ $tip['gainLabel'] }}: {{ $sign }}{{ $money($tip['moneyGain']) }}</p>
                                @elseif ($tip['teaGain'] !== null && $tip['teaGain'] >= 0.00005)
                                    <p class="mt-2 inline-block rounded-full px-3 py-1 text-lg font-bold {{ $chip }}">{{ $tip['gainLabel'] }}: {{ $sign }}{{ number_format($tip['teaGain'] * 100, 2, ',', '.') }} puntos de TEA</p>
                                @endif
                                @if ($tip['details'] !== [])
                                    <ul class="mt-2 space-y-0.5 text-base text-gray-700">
                                        @foreach ($tip['details'] as $detail)
                                            <li wire:key="tip-{{ $tip['key'] }}-{{ $loop->index }}">• {{ $detail }}</li>
                                        @endforeach
                                    </ul>
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
