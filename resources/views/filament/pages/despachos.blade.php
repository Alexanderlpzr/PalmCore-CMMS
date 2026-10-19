@php
    $resumen = $this->resumen();
@endphp

<x-filament-panels::page>
    {{-- Lo de la cola que muestra la tabla, con los filtros que tenga puestos. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-fronda.kpi
            label="Por despachar"
            :value="number_format($resumen['pending'], 0, ',', '.')"
            icon="heroicon-o-clipboard-document-check"
            :tone="$resumen['pending'] > 0 ? 'warning' : 'ok'"
            :detail="$resumen['orders'].' '.($resumen['orders'] === 1 ? 'orden de trabajo' : 'órdenes de trabajo')"
        />
        <x-fronda.kpi
            label="De órdenes ya cerradas"
            :value="number_format($resumen['closedOrders'], 0, ',', '.')"
            icon="heroicon-o-lock-closed"
            :tone="$resumen['closedOrders'] > 0 ? 'warning' : 'ok'"
            :percent="$resumen['orders'] > 0 ? $resumen['closedOrders'] * 100 / $resumen['orders'] : null"
            :color="\App\Support\FrondaPalette::Petrol[600]"
            detail="Confirme lo que se entregó o márquelo como no entregado"
        />
        <x-fronda.kpi
            label="Sin existencia suficiente"
            :value="number_format($resumen['short'], 0, ',', '.')"
            icon="heroicon-o-archive-box-x-mark"
            :tone="$resumen['short'] > 0 ? 'danger' : 'ok'"
            :percent="$resumen['pending'] > 0 ? $resumen['short'] * 100 / $resumen['pending'] : null"
            color="#dc2626"
            :detail="$resumen['short'] > 0 ? 'Hay que comprar o trasladar antes de entregar' : 'Todo lo pedido está en el estante'"
        />
        <x-fronda.kpi
            label="Valor por entregar"
            :value="'$ '.number_format($resumen['value'], 0, ',', '.')"
            icon="heroicon-o-banknotes"
            :color="\App\Support\FrondaPalette::Brand[600]"
            detail="Al costo de cada pedido"
        />
    </div>

    {{ $this->table }}
</x-filament-panels::page>
