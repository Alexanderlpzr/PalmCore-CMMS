{{--
    El indicador ya calculado, que entra suave en el lugar de su silueta. Sin nómina a la
    vista no se pinta nada: el contenedor queda vacío.
--}}
<div
    x-data="{ visible: false }"
    x-init="requestAnimationFrame(() => visible = true)"
    x-bind:class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-1'"
    class="transition duration-500 ease-out motion-reduce:transition-none"
>
    @if ($factor)
        <x-fronda.hours-factor-indicator :factor="$factor" />
    @endif
</div>
