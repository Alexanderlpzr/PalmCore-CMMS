{{--
    La planilla de energía, con las etiquetas que la planta ya lee.

    Doce meses no caben en un móvil, así que la tabla desborda dentro de su propio
    contenedor con scroll horizontal y la primera columna queda fija: sin eso, al
    desplazarse a NOVIEMBRE se pierde de vista qué fila se está leyendo.
--}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Energía · {{ $year }}
        </x-slot>

        <x-slot name="description">
            Un mes sin dato muestra «—». La columna «Año» acumula solo los meses con dato.
        </x-slot>

        @if ($empty)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No hay ninguna planta registrada.
            </p>
        @else
            <div class="overflow-x-auto -mx-2 px-2">
                <table class="w-full text-sm border-separate border-spacing-0">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">
                                PARÁMETROS
                            </th>
                            @foreach ($months as $label)
                                <th class="text-right font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                    {{ $label }}
                                </th>
                            @endforeach
                            <th class="text-right font-bold px-3 py-2 border-b border-l border-gray-200 dark:border-white/10 whitespace-nowrap">
                                AÑO
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="{{ $row['strong'] ? 'bg-gray-50 dark:bg-white/5' : '' }}">
                                <th class="sticky left-0 z-10 {{ $row['strong'] ? 'bg-gray-50 dark:bg-gray-900' : 'bg-white dark:bg-gray-900' }} text-left px-3 py-2 border-b border-gray-100 dark:border-white/5 whitespace-nowrap {{ $row['strong'] ? 'font-bold' : 'font-medium' }}">
                                    {{ $row['label'] }}
                                </th>

                                @foreach ($row['values'] as $value)
                                    <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap {{ $row['strong'] ? 'font-semibold' : '' }}">
                                        @if ($value === null)
                                            <span class="text-gray-300 dark:text-gray-600">—</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                @endforeach

                                <td class="text-right px-3 py-2 border-b border-l border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap font-bold">
                                    @if ($row['total'] === null)
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @else
                                        {{ $row['total'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
