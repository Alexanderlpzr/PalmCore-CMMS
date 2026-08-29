@php($tabla = $this->monthTable())
@php($diasAnotados = collect($tabla['days'])->filter(fn (array $day): bool => $day['accumulated_tons'] !== null)->count())

{{--
    El mes con el RFF acumulándose.

    La columna del acumulado es la razón de esta tabla: la planta pregunta por dónde va la
    fruta a mitad de mes, y antes había que esperar al cierre o sumar a mano.
--}}
<x-filament::section>
    <x-slot name="heading">
        Jornadas por día
    </x-slot>

    <x-slot name="description">
        Un día sin cargar muestra «—», no cero. Pulsa una fila para llevar el formulario de arriba a ese día.
    </x-slot>

    {{-- El mes bajo su propia cabecera, con la flecha que lo pliega: el mismo gesto que
         agrupa los equipos por sección. Aquí solo hay un mes, y aun así vale la pena
         poder plegarlo — treinta filas empujan la jornada fuera de la pantalla. --}}
    @include('filament.components.cabecera-grupo', [
        'titulo' => $tabla['monthLabel'] ?: 'Este mes',
        'descripcion' => match ($diasAnotados) {
            0 => 'Sin jornadas todavía',
            1 => '1 día anotado · '.number_format($tabla['total_tons'], 2, ',', '.').' t',
            default => $diasAnotados.' días anotados · '.number_format($tabla['total_tons'], 2, ',', '.').' t',
        },
        'plegada' => $this->mesPlegado,
        'accion' => 'toggleMes',
    ])

    @if ($this->mesPlegado)
        {{-- Plegado: la cabecera de arriba ya dice cuántos días llevan jornada. --}}
    {{-- El mes genera sus días aunque ninguno tenga jornada escrita, así que mirar solo
         si la lista está vacía dejaba la tabla pintando treinta filas de guiones. Lo que
         importa es si hay alguna jornada, no si hay días. --}}
    @elseif ($diasAnotados === 0)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $tabla['monthLabel'] ?: 'Este mes' }} todavía no tiene ninguna jornada anotada.
        </p>
    @else
        <div class="overflow-x-auto -mx-2 mt-3 px-2">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">
                            FECHA
                        </th>
                        <th class="text-right font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            HORAS
                        </th>
                        <th class="text-right font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            FRUTA (t)
                        </th>
                        <th class="text-right font-semibold px-3 py-2 border-b border-l border-gray-200 dark:border-white/10 whitespace-nowrap">
                            RFF ACUMULADO
                        </th>
                        <th class="text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap text-gray-600 dark:text-gray-300">
                            NOTAS
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($tabla['days'] as $day)
                        <tr wire:key="dia-{{ $day['date'] }}"
                            wire:click="goToDay('{{ $day['date'] }}')"
                            @class([
                                'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5',
                                'bg-primary-50 dark:bg-primary-500/10' => $day['date'] === ($this->data['calendar_date'] ?? null),
                            ])
                        >
                            <th class="sticky left-0 z-10 bg-inherit text-left px-3 py-2 border-b border-gray-100 dark:border-white/5 whitespace-nowrap font-medium">
                                {{ ucfirst($day['label']) }}
                            </th>

                            <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap">
                                @if ($day['hours'] === null)
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @else
                                    {{ number_format($day['hours'], 2, ',', '.') }}
                                @endif
                            </td>

                            <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap">
                                @if ($day['tons'] === null)
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @else
                                    {{ number_format($day['tons'], 2, ',', '.') }}
                                @endif
                            </td>

                            <td class="text-right px-3 py-2 border-b border-l border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap font-semibold">
                                @if ($day['accumulated_tons'] === null)
                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                @else
                                    {{ number_format($day['accumulated_tons'], 2, ',', '.') }}
                                @endif
                            </td>

                            <td class="text-left px-3 py-2 border-b border-gray-100 dark:border-white/5 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $day['notes'] ?: '' }}
                            </td>
                        </tr>
                    @endforeach

                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-900 text-left px-3 py-2 whitespace-nowrap font-bold">
                            TOTAL DEL MES
                        </th>
                        <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">
                            {{ number_format($tabla['total_hours'], 2, ',', '.') }}
                        </td>
                        <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">
                            {{ number_format($tabla['total_tons'], 2, ',', '.') }}
                        </td>
                        <td class="border-l border-gray-100 dark:border-white/5"></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>
