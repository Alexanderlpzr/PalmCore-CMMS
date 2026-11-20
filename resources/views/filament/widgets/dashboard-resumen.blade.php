{{--
    El resumen del Dashboard: cinco cifras del período y las pestañas. Cada tarjeta abre la
    pestaña donde están sus gráficas. Ver App\Filament\Widgets\Executive\DashboardSummaryWidget.
--}}
@php($resumen = $this->summary())

<x-filament-widgets::widget>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($resumen['cards'] as $tarjeta)
                <x-fronda.kpi
                    :label="$tarjeta['label']"
                    :value="$tarjeta['value']"
                    :detail="$tarjeta['detail']"
                    :icon="$tarjeta['icon']"
                    :tone="$tarjeta['tone']"
                    :active="$seccion === $tarjeta['section']"
                    role="button"
                    tabindex="0"
                    title="Ver {{ mb_strtolower($resumen['sections'][$tarjeta['section']]) }}"
                    wire:click="$parent.set('seccion', '{{ $tarjeta['section'] }}')"
                    x-on:keydown.enter="$wire.$parent.set('seccion', '{{ $tarjeta['section'] }}')"
                    class="cursor-pointer transition hover:-translate-y-0.5 hover:shadow-md"
                />
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-filament::tabs>
                @foreach ($resumen['sections'] as $clave => $nombre)
                    <x-filament::tabs.item
                        :active="$seccion === $clave"
                        wire:click="$parent.set('seccion', '{{ $clave }}')"
                    >
                        {{ $nombre }}
                    </x-filament::tabs.item>
                @endforeach

                {{-- Las pantallas de indicadores que salieron del menú (2026-11-19). --}}
                @foreach (\App\Filament\Widgets\Executive\IndicadoresNavWidget::extras() as $pestana)
                    <x-filament::tabs.item tag="a" :href="$pestana['url']">
                        {{ $pestana['label'] }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $resumen['period'] }}</p>
        </div>
    </div>
</x-filament-widgets::widget>
