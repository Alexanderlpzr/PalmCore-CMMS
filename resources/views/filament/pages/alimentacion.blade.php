@php
    $resumen = $this->resumen();
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $porComida = collect(\App\Domain\HumanResources\Services\MealAllowanceCalculator::MEALS)
        ->map(fn (string $etiqueta, string $comida): string => $resumen['counts'][$comida].' '.mb_strtolower($etiqueta))
        ->filter(fn (string $texto, string $comida): bool => $resumen['counts'][$comida] > 0)
        ->join(' · ');
@endphp

<x-filament-panels::page>
    {{-- Lo del período elegido en el filtro: lo que hay que pagar y por qué. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-fronda.kpi
            label="Valor a pagar"
            :value="$pesos($resumen['amount'])"
            color="#d97706"
            detail="Aparte de la nómina"
        />
        <x-fronda.kpi
            label="Comidas"
            :value="number_format($resumen['meals'], 0, ',', '.')"
            icon="heroicon-o-cake"
            :detail="$porComida ?: 'Sin comidas todavía'"
        />
        <x-fronda.kpi
            label="Trabajadores"
            :value="$resumen['people']"
            icon="heroicon-o-user-group"
            detail="Con al menos una comida"
        />
        <x-fronda.kpi
            label="Promedio por trabajador"
            :value="$pesos($resumen['people'] > 0 ? $resumen['amount'] / $resumen['people'] : 0)"
            icon="heroicon-o-calculator"
            detail="Solo días confirmados"
        />
    </div>

    {{ $this->table }}
</x-filament-panels::page>
