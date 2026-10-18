{{--
    La cifra principal de un tablero, en la tarjeta con el degradado verde–petróleo del logo.
    Una por tablero: es lo primero que se lee. El número sube con una animación corta cada
    vez que cambia, para que se note que se actualizó.

    Si la pestaña está en segundo plano el navegador pausa la animación; un temporizador
    deja el número en su valor de todos modos.

    Sin dato (`value` null) muestra «—» y no un cero: un mes sin capturar no es un mes en
    cero.

    Ranuras: el cuerpo (una barra de avance, una línea de detalle) y `stats`, los
    indicadores secundarios al pie (x-fronda.hero-stat).
--}}
@props([
    'label',
    'value' => 0,
    'prefix' => '',
    'suffix' => '',
    'decimals' => 0,
    'badge' => null,
    'icon' => 'heroicon-o-chart-bar',
    // Blade descarta un `:value="null"`, así que «sin dato» se dice aparte.
    'missing' => false,
])

@php
    $sinDato = $missing || $value === null;
    $valor = (float) $value;
    $texto = $sinDato ? '—' : $prefix.number_format($valor, (int) $decimals, ',', '.').$suffix;
@endphp

<div {{ $attributes->class('relative flex flex-col overflow-hidden rounded-2xl bg-gradient-to-br from-[#1a7e42] via-[#0d5a4a] to-[#00384c] p-6 text-white shadow-lg') }}>
    <x-filament::icon :icon="$icon" class="pointer-events-none absolute -bottom-6 -right-6 h-40 w-40 text-white/10" />

    <div class="relative flex flex-wrap items-start justify-between gap-2">
        <p class="text-sm font-medium text-white/80">{{ $label }}</p>
        @if ($badge)
            <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium text-white ring-1 ring-white/25">{{ $badge }}</span>
        @endif
    </div>

    @if ($sinDato)
        <p class="relative mt-1 text-4xl font-bold tracking-tight sm:text-5xl">—</p>
    @else
        <p
            wire:key="hero-{{ md5($label.$texto) }}"
            x-data="{ valor: {{ $valor * 0.85 }}, fin: {{ $valor }} }"
            x-init="
                const inicio = valor, arranque = performance.now();
                const paso = (ahora) => {
                    const t = Math.min(1, (ahora - arranque) / 800);
                    valor = inicio + (fin - inicio) * (1 - Math.pow(1 - t, 3));
                    if (t < 1) requestAnimationFrame(paso);
                };
                requestAnimationFrame(paso);
                setTimeout(() => valor = fin, 1000);
            "
            x-text="@js($prefix) + valor.toLocaleString('es-CO', { minimumFractionDigits: {{ (int) $decimals }}, maximumFractionDigits: {{ (int) $decimals }} }) + @js($suffix)"
            class="relative mt-1 whitespace-nowrap text-4xl font-bold tabular-nums tracking-tight sm:text-5xl"
        >{{ $texto }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="relative mt-5">{{ $slot }}</div>
    @endif

    @isset($stats)
        <div class="relative mt-auto grid grid-cols-2 gap-3 pt-5">{{ $stats }}</div>
    @endisset
</div>
