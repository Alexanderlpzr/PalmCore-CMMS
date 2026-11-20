{{--
    Pestañas dentro de una pantalla: arriba, los nombres; debajo, solo el bloque de la
    pestaña elegida. Cambiar de pestaña no recarga nada —todo ya está en la página— y la
    elegida queda en la dirección (`?ver=cartera`), así que recargar o compartir el enlace
    la conserva. Lo mismo que el Dashboard, para las pantallas hechas a mano (2026-11-19).

    Uso:
        <x-fronda.pestanas :pestanas="['aceite' => 'Aceite', 'cartera' => 'Cartera']">
            <x-fronda.pestana nombre="aceite"> … </x-fronda.pestana>
            <x-fronda.pestana nombre="cartera"> … </x-fronda.pestana>
        </x-fronda.pestanas>

    `aside` va a la derecha de las pestañas (el período, una nota). Una tarjeta fuera del
    bloque abre una pestaña con x-on:click="$dispatch('fronda-ver', 'cartera')".
--}}
@props([
    'pestanas',
    'inicial' => null,
    'parametro' => 'ver',
    'aside' => null,
])

@php
    $claves = array_keys($pestanas);
    $inicial ??= $claves[0] ?? null;
@endphp

<div
    x-data="{
        ver: @js($inicial),
        init() {
            const pedida = new URL(window.location.href).searchParams.get(@js($parametro));

            if (@js($claves).includes(pedida)) {
                this.ver = pedida;
            }

            this.$watch('ver', (valor) => {
                const url = new URL(window.location.href);
                url.searchParams.set(@js($parametro), valor);
                window.history.replaceState(window.history.state, '', url);
                // Las gráficas que estaban ocultas se dibujan al aparecer.
                this.$nextTick(() => window.dispatchEvent(new Event('resize')));
            });
        },
    }"
    {{-- Una tarjeta de fuera puede abrir una pestaña: $dispatch('fronda-ver', 'cartera'). --}}
    x-on:fronda-ver.window="if (@js($claves).includes($event.detail)) ver = $event.detail"
    {{ $attributes->class(['space-y-6']) }}
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <x-filament::tabs>
            @foreach ($pestanas as $clave => $nombre)
                <x-filament::tabs.item
                    :alpine-active="'ver === '.\Illuminate\Support\Js::from($clave)"
                    x-on:click="ver = {{ \Illuminate\Support\Js::from($clave) }}"
                >
                    {{ $nombre }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        @if ($aside)
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $aside }}</div>
        @endif
    </div>

    {{ $slot }}
</div>
