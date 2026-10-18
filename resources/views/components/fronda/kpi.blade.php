{{--
    Una tarjeta de indicador, la misma en todos los tableros: rótulo con su punto de color,
    la cifra, una barra opcional con la parte del total que representa y un detalle.

    `tone` pinta el borde y la cifra cuando el número pide atención (warning, danger) o ya
    está bien (ok). Con `href`, la tarjeta entera lleva a la lista donde se resuelve.
--}}
@props([
    'label',
    'value',
    'detail' => null,
    'color' => null,
    'percent' => null,
    'icon' => null,
    'tone' => 'neutral',
    'href' => null,
    'active' => false,
])

@php
    $etiqueta = $href ? 'a' : 'div';
@endphp

<{{ $etiqueta }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'group flex flex-col gap-1 rounded-xl border bg-white p-4 shadow-sm dark:bg-gray-900',
        'transition hover:-translate-y-0.5 hover:shadow-md' => $href,
        // Su filtro es el que está puesto en la tabla: se toca otra vez para quitarlo.
        'ring-2 ring-primary-500' => $active,
        'border-gray-200 dark:border-white/10' => in_array($tone, ['neutral', 'ok'], true),
        'border-warning-300 dark:border-warning-500/40' => $tone === 'warning',
        'border-danger-300 dark:border-danger-500/40' => $tone === 'danger',
    ]) }}
>
    <div class="flex items-center justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2">
            @if ($color)
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $color }}"></span>
            @endif
            <p class="text-sm font-medium leading-tight text-gray-600 dark:text-gray-300">{{ $label }}</p>
        </div>
        @if ($percent !== null)
            <span class="text-xs tabular-nums text-gray-400">{{ (int) round($percent) }} %</span>
        @elseif ($icon)
            <x-filament::icon
                :icon="$icon"
                @class([
                    'h-5 w-5 shrink-0',
                    'text-gray-400' => $tone === 'neutral',
                    'text-success-600' => $tone === 'ok',
                    'text-warning-600' => $tone === 'warning',
                    'text-danger-600' => $tone === 'danger',
                ])
            />
        @endif
    </div>

    <p @class([
        'text-xl font-bold tabular-nums sm:text-2xl xl:text-3xl',
        // Una cifra no se parte; un texto como «Sin horas pagadas» sí puede bajar de línea.
        'whitespace-nowrap' => mb_strlen((string) $value) <= 14,
        'leading-tight' => mb_strlen((string) $value) > 14,
        'text-gray-950 dark:text-white' => in_array($tone, ['neutral', 'ok'], true),
        'text-warning-600 dark:text-warning-400' => $tone === 'warning',
        'text-danger-600 dark:text-danger-400' => $tone === 'danger',
    ])>{{ $value }}</p>

    @if ($percent !== null)
        <x-fronda.progress :percent="$percent" :color="$color" class="mt-1" />
    @endif

    @if ($detail)
        <p @class([
            'mt-1 text-xs text-gray-500 dark:text-gray-400',
            'group-hover:text-primary-600' => $href,
        ])>{{ $detail }}@if ($href) →@endif</p>
    @endif

    {{ $slot }}
</{{ $etiqueta }}>
