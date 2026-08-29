@php($tabla = $this->monthTable())

{{--
    El mes con el RFF acumulándose.

    La columna del acumulado es la razón de esta tabla: la planta pregunta por dónde va la
    fruta a mitad de mes, y antes había que esperar al cierre o sumar a mano.
--}}
<x-filament::section>
    <x-slot name="heading">
        {{ $tabla['monthLabel'] }}
    </x-slot>

    <x-slot name="description">
        Un día sin cargar muestra «—», no cero. Pulsa una fila para llevar el formulario de arriba a ese día.
    </x-slot>

    @if (empty($tabla['days']))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Todavía no hay ningún día de este mes.
        </p>
    @else
        <div class="overflow-x-auto -mx-2 px-2">
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
