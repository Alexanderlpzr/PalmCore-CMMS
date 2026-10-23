{{--
    La silueta del indicador mientras se calcula el corte: la misma forma de la tabla,
    con un pulso suave, para que la espera se lea como «ya viene» y no como una página
    rota. Sin movimiento para quien lo pidió en su sistema.
--}}
<div>
    <x-filament::section>
        <x-slot name="heading">Indicador factor de horas</x-slot>
        <x-slot name="description">Calculando lo que va del corte…</x-slot>

        <div class="space-y-2" role="status" aria-label="Cargando el indicador factor de horas">
            <div class="h-8 rounded-md bg-gray-100 dark:bg-white/5"></div>
            @foreach ([88, 76, 64, 92, 70, 84, 60, 96] as $i => $ancho)
                <div class="flex items-center gap-3">
                    <span class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-gray-200 motion-reduce:animate-none dark:bg-white/10"></span>
                    <span class="h-3 animate-pulse rounded bg-gray-200 motion-reduce:animate-none dark:bg-white/10" style="width: {{ $ancho * 0.6 }}%; animation-delay: {{ $i * 90 }}ms"></span>
                    <span class="ms-auto h-3 w-12 animate-pulse rounded bg-gray-200 motion-reduce:animate-none dark:bg-white/10" style="animation-delay: {{ $i * 90 }}ms"></span>
                    <span class="h-3 w-24 animate-pulse rounded bg-gray-200 motion-reduce:animate-none dark:bg-white/10" style="animation-delay: {{ $i * 90 }}ms"></span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</div>
