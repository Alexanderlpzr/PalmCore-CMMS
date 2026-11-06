@php
    $conteos = $this->counts();
@endphp

<x-filament-panels::page>
    {{-- Sin leer primero: lo que falta por atender. --}}
    <x-filament::tabs label="Notificaciones">
        @foreach (['sin-leer' => 'Sin leer', 'todas' => 'Todas', 'leidas' => 'Leídas'] as $clave => $rotulo)
            <x-filament::tabs.item
                :active="$this->vista === $clave"
                wire:click="setVista('{{ $clave }}')"
                :badge="$conteos[$clave] > 0 ? number_format($conteos[$clave], 0, ',', '.') : null"
                :badge-color="$clave === 'sin-leer' ? 'primary' : 'gray'"
            >
                {{ $rotulo }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->table }}
</x-filament-panels::page>
