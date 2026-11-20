{{-- La barra de los indicadores, arriba de Eficiencia, Presupuesto y Energía. --}}
<x-filament-widgets::widget>
    <x-filament::tabs>
        @foreach ($this->pestanas() as $clave => $pestana)
            <x-filament::tabs.item tag="a" :href="$pestana['url']" :active="$clave === $activo">
                {{ $pestana['label'] }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>
</x-filament-widgets::widget>
