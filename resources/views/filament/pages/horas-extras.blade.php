@php
    $resumen = $this->resumen();
    $horas = fn (float $h): string => rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',').' h';
    $pagadas = $resumen['overtime'] + $resumen['surcharges'] + $resumen['bonus'];
@endphp

<x-filament-panels::page>
    {{-- Lo del período elegido en el filtro, de todos los trabajadores. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-fronda.kpi
            label="Horas extras"
            :value="$horas($resumen['overtime'])"
            :color="\App\Support\FrondaPalette::Brand[600]"
            :percent="$pagadas > 0 ? $resumen['overtime'] * 100 / $pagadas : 0"
            detail="Diurnas, nocturnas y dominicales"
        />
        <x-fronda.kpi
            label="Recargos"
            :value="$horas($resumen['surcharges'])"
            :color="\App\Support\FrondaPalette::Petrol[300]"
            :percent="$pagadas > 0 ? $resumen['surcharges'] * 100 / $pagadas : 0"
            :detail="$resumen['bonus'] > 0 ? 'Y '.$horas($resumen['bonus']).' como bonificación' : 'Nocturnos y dominicales'"
        />
        <x-fronda.kpi
            label="Trabajadores con horas"
            :value="$resumen['people']"
            icon="heroicon-o-user-group"
            :detail="'De '.$resumen['active'].' activos'"
        />
        <x-fronda.kpi
            label="Días por confirmar"
            :value="$resumen['unconfirmed']"
            icon="heroicon-o-clipboard-document-check"
            :tone="$resumen['unconfirmed'] > 0 ? 'warning' : 'ok'"
            :detail="$resumen['unconfirmed'] > 0 ? 'No entran a la nómina hasta confirmarlos' : 'Todo el período está confirmado'"
            :href="$resumen['unconfirmed'] > 0 ? \App\Filament\Pages\RevisarHoras::getUrl() : null"
        />
    </div>

    {{ $this->table }}
</x-filament-panels::page>
