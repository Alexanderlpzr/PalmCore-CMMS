@php
    $pesos = fn (float $valor): string => ($valor < 0 ? '−' : '').'$ '.number_format(abs($valor), 0, ',', '.');
@endphp

<x-filament-widgets::widget>
    <x-fronda.hero
        :label="'Avance del conteo '.$count->count_number.' · '.$count->warehouse?->name"
        :value="$counted"
        :suffix="' de '.number_format($total, 0, ',', '.')"
        :badge="$count->status->label()"
        icon="heroicon-o-clipboard-document-list"
    >
        <div class="space-y-4">
            <x-fronda.progress
                light
                :percent="$total > 0 ? $counted * 100 / $total : 0"
                :label="$counted.' de '.$total.' repuestos contados'"
            />
            <x-fronda.progress
                light
                :percent="$bins > 0 ? $binsDone * 100 / $bins : 0"
                :label="$binsDone.' de '.$bins.' '.($bins === 1 ? 'ubicación completa' : 'ubicaciones completas')"
            />
        </div>

        <x-slot name="stats">
            @if ($open)
                <x-fronda.hero-stat label="Sin contar" :value="number_format($total - $counted, 0, ',', '.')" detail="Lo que no se cuente no se toca al aplicar" />
                <x-fronda.hero-stat label="Contados hoy" :value="number_format($countedToday, 0, ',', '.')" :detail="'Desde que se abrió el '.$count->created_at?->format('d/m/Y')" />
                <x-fronda.hero-stat label="Conteo ciego" value="Activo" detail="Las diferencias se ven al cerrar o con «Ver diferencias»" />
            @else
                <x-fronda.hero-stat label="Cuadraron" :value="number_format($exact, 0, ',', '.')" :detail="$counted > 0 ? number_format($exact * 100 / $counted, 0, ',', '.').' % de lo contado' : 'Nada contado'" />
                <x-fronda.hero-stat label="Faltantes" :value="$pesos($shortValue)" :detail="$short.' '.($short === 1 ? 'repuesto' : 'repuestos').' con menos de lo esperado'" />
                <x-fronda.hero-stat label="Sobrantes" :value="$pesos($overValue)" :detail="$over.' '.($over === 1 ? 'repuesto' : 'repuestos').' con más de lo esperado'" />
            @endif
        </x-slot>
    </x-fronda.hero>
</x-filament-widgets::widget>
