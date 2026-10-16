@php
    $resumen = $this->resumen();
    $horas = fn (float $h): string => rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',').' h';
@endphp

<x-filament-panels::page>
    {{-- Lo de los días que muestra la tabla, con los filtros que tenga puestos. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-fronda.kpi
            label="Días confirmados"
            :value="number_format($resumen['days'], 0, ',', '.')"
            icon="heroicon-o-check-badge"
            tone="ok"
            :detail="$resumen['people'].' '.($resumen['people'] === 1 ? 'trabajador' : 'trabajadores')"
        />
        <x-fronda.kpi
            label="Horas trabajadas"
            :value="$horas($resumen['worked'])"
            :color="\App\Support\FrondaPalette::Petrol[600]"
            detail="Ordinarias, recargos y extras"
        />
        <x-fronda.kpi
            label="Extras y recargos"
            :value="$horas($resumen['overtime'] + $resumen['surcharges'])"
            :color="\App\Support\FrondaPalette::Brand[600]"
            :percent="$resumen['worked'] > 0 ? ($resumen['overtime'] + $resumen['surcharges']) * 100 / $resumen['worked'] : 0"
            :detail="$horas($resumen['overtime']).' extras · '.$horas($resumen['surcharges']).' con recargo'"
        />
        <x-fronda.kpi
            label="Ajustados a mano"
            :value="$resumen['adjusted']"
            icon="heroicon-o-pencil-square"
            :detail="$resumen['adjusted'] > 0 ? 'Con su motivo, al pasar el ratón por las horas' : 'Ningún día corregido a mano'"
        />
    </div>

    {{ $this->table }}
</x-filament-panels::page>
