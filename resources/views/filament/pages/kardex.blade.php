@php
    $resumen = $this->resumen();
    $pesos = fn (float $valor): string => ($valor < 0 ? '−' : '').'$ '.number_format(abs($valor), 0, ',', '.');
    $movido = $resumen['inValue'] + $resumen['outValue'];
@endphp

<x-filament-panels::page>
    {{-- Lo de los movimientos que muestra la tabla, con los filtros que tenga puestos. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-fronda.kpi
            label="Movimientos"
            :value="number_format($resumen['movements'], 0, ',', '.')"
            icon="heroicon-o-arrows-right-left"
            :detail="$resumen['parts'].' '.($resumen['parts'] === 1 ? 'repuesto' : 'repuestos')"
        />
        <x-fronda.kpi
            label="Entró"
            :value="$pesos($resumen['inValue'])"
            icon="heroicon-o-arrow-down-tray"
            tone="ok"
            :percent="$movido > 0 ? $resumen['inValue'] * 100 / $movido : null"
            :color="\App\Support\FrondaPalette::Brand[600]"
            :detail="$resumen['inCount'].' entradas y devoluciones'"
        />
        <x-fronda.kpi
            label="Salió"
            :value="$pesos($resumen['outValue'])"
            icon="heroicon-o-arrow-up-tray"
            :percent="$movido > 0 ? $resumen['outValue'] * 100 / $movido : null"
            :color="\App\Support\FrondaPalette::Petrol[600]"
            :detail="$resumen['outCount'].' salidas y despachos'"
        />
        <x-fronda.kpi
            label="Ajustes por conteo"
            :value="$pesos($resumen['adjustValue'])"
            icon="heroicon-o-clipboard-document-list"
            :tone="$resumen['adjustValue'] < 0 ? 'danger' : ($resumen['adjustCount'] > 0 ? 'warning' : 'ok')"
            :detail="$resumen['adjustCount'] > 0 ? $resumen['adjustCount'].' con diferencia, sobrantes menos faltantes' : 'Ningún conteo con diferencia'"
        />
    </div>

    {{ $this->table }}
</x-filament-panels::page>
