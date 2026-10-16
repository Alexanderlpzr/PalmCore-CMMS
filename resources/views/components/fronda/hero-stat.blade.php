{{-- Un indicador secundario al pie de la tarjeta principal (x-fronda.hero). --}}
@props(['label', 'value', 'detail' => null])

<div {{ $attributes->class('rounded-xl bg-white/10 p-3 ring-1 ring-white/15') }}>
    <p class="text-xs text-white/70">{{ $label }}</p>
    <p class="whitespace-nowrap text-lg font-bold tabular-nums sm:text-2xl">{{ $value }}</p>
    @if ($detail)
        <p class="text-xs text-white/70">{{ $detail }}</p>
    @endif
</div>
