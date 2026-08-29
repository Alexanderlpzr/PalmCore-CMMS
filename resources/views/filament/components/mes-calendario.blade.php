@php($cal = $this->monthCalendar())

{{--
    El mes de un vistazo, para llegar a cualquier día de un clic.

    Lo que hace útil esta rejilla no es navegar: es que **se ven los huecos**. Quien vuelve
    tras unos días fuera necesita saber qué falta por anotar, y hasta ahora eso obligaba a
    pulsar «Día anterior» a ciegas.

    Va en calendario y no en una tira de números porque los domingos no muelen: saber qué
    día de la semana es cada casilla cambia cómo se lee un hueco. Un domingo vacío es
    normal; un martes vacío es un olvido.

    La vista la comparten Energía y Producción — recibe los días ya resueltos y el nombre
    del método al que llamar, así que no sabe cuál de las dos está pintando.
--}}
<x-filament::section>
    <x-slot name="heading">
        {{ $cal['monthLabel'] }}
    </x-slot>

    <x-slot name="description">
        {{ $cal['legend'] }}
    </x-slot>

    <div class="max-w-md">
        <div class="grid grid-cols-7 gap-1 text-center">
            @foreach (['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'] as $dow)
                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 pb-1">{{ $dow }}</div>
            @endforeach

            {{-- El hueco hasta que el día 1 cae en su columna. --}}
            @for ($i = 0; $i < $cal['offset']; $i++)
                <div></div>
            @endfor

            @foreach ($cal['days'] as $day)
                @if ($day['is_future'])
                    {{-- Ni un contador ni una jornada se anotan por adelantado: la misma
                         regla que ya rige el botón «Día siguiente». --}}
                    <div class="py-2 rounded-lg text-sm text-gray-300 dark:text-gray-700 cursor-not-allowed"
                         title="Aún no ocurre">
                        {{ $day['day'] }}
                    </div>
                @else
                    <button type="button"
                            wire:click="goToDay('{{ $day['date'] }}')"
                            title="{{ $day['has_data'] ? 'Con dato' : 'Sin anotar' }}"
                            @class([
                                'py-2 rounded-lg text-sm transition',
                                // Seleccionado: manda sobre el resto.
                                'bg-primary-600 text-white font-bold' => $day['is_selected'],
                                // Con dato, sin seleccionar.
                                'bg-primary-50 text-primary-700 font-medium hover:bg-primary-100 dark:bg-primary-500/15 dark:text-primary-300 dark:hover:bg-primary-500/25'
                                    => ! $day['is_selected'] && $day['has_data'],
                                // El hueco: lo que se busca al ponerse al día.
                                'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5'
                                    => ! $day['is_selected'] && ! $day['has_data'],
                            ])
                    >
                        {{ $day['day'] }}
                    </button>
                @endif
            @endforeach
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="inline-block w-3 h-3 align-middle rounded bg-primary-100 dark:bg-primary-500/25"></span>
            con dato ·
            <span class="inline-block w-3 h-3 align-middle rounded border border-gray-300 dark:border-white/20"></span>
            sin anotar
        </p>
    </div>
</x-filament::section>
