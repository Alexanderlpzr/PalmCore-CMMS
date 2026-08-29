@php
    $descripcion ??= null;
    $plegada ??= false;
@endphp

{{--
    La banda de cabecera de un grupo, como la de «Sección: Clarificación» en Equipos.

    Filament no dibuja sus grupos con <table>: son divs en una rejilla CSS, y sus clases
    —`fi-ta-group-header` y compañía— están definidas dentro de un ámbito propio del que
    no salen. Pegarlas sobre un <tr> no pinta nada. Así que se reproducen las utilidades,
    que es lo que hace el aspecto: la banda gris, el título en medium, la descripción en
    gris claro y la flecha girando ciento ochenta grados al plegar.

    No sabe de meses ni de contadores: recibe el texto, si está plegada y a qué método
    llamar. Por eso la comparten las tres tablas.

    Se incluye con @include, no como componente: vive junto a las vistas de Filament que
    la usan, y ese directorio no es de los que Blade resuelve como <x-...>.
--}}
<div
    wire:click="{{ $accion }}"
    role="button"
    tabindex="0"
    aria-expanded="{{ $plegada ? 'false' : 'true' }}"
    class="flex w-full cursor-pointer items-center gap-x-3 rounded-lg bg-gray-50 px-3 py-2 hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10"
>
    <div class="flex-1">
        <h3 class="text-sm font-medium text-gray-950 dark:text-white">
            {{ $titulo }}
        </h3>

        @if (filled($descripcion))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $descripcion }}
            </p>
        @endif
    </div>

    <span @class([
        'text-gray-400 transition-transform dark:text-gray-500',
        '-rotate-180' => $plegada,
    ])>
        <x-filament::icon
            icon="heroicon-m-chevron-up"
            class="h-5 w-5"
        />
    </span>
</div>
