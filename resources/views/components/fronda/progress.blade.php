{{--
    Una barra de avance con su rótulo: «22 de 24 entraron · 92 %». `light` es para usarla
    dentro de la tarjeta con degradado; `tone` colorea la barra en las tarjetas blancas.
--}}
@props(['percent' => 0, 'label' => null, 'light' => false, 'tone' => 'primary', 'color' => null])

@php
    $avance = max(0, min(100, (int) round((float) $percent)));
@endphp

<div {{ $attributes }}>
    @if ($label !== null)
        <div @class([
            'mb-1.5 flex justify-between gap-2 text-xs',
            'text-white/80' => $light,
            'text-gray-500 dark:text-gray-400' => ! $light,
        ])>
            <span>{{ $label }}</span>
            <span class="tabular-nums">{{ $avance }} %</span>
        </div>
    @endif
    <div @class([
        'h-2 overflow-hidden rounded-full',
        'bg-white/20' => $light,
        'bg-gray-100 dark:bg-white/10' => ! $light,
    ])>
        <div
            @class([
                'h-full rounded-full transition-all duration-700',
                'bg-white' => $light,
                'bg-primary-600' => ! $light && ! $color && $tone === 'primary',
                'bg-success-600' => ! $light && ! $color && $tone === 'success',
                'bg-warning-500' => ! $light && ! $color && $tone === 'warning',
                'bg-danger-600' => ! $light && ! $color && $tone === 'danger',
            ])
            style="width: {{ $avance }}%;{{ $color && ! $light ? ' background-color: '.$color.';' : '' }}"
        ></div>
    </div>
</div>
