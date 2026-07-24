{{-- Matriz de horómetros estilo Excel: equipos en filas, fechas en columnas.
     Espera: $columns, $rows, $columnTotals, $grandTotal, $rangeLabel, $isDaily, $canGoNext --}}
<div class="mt-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previousWindow" />
            <x-filament::button color="gray" size="sm" wire:click="goToToday">Hoy</x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="nextWindow" :disabled="! $canGoNext" />
            <span class="ml-1 text-sm font-medium text-gray-700 dark:text-gray-200">{{ $rangeLabel }}</span>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Escribe el valor del día en una celda vacía y Enter (horómetro o horas trabajadas, según el equipo).
            Para corregir un dato ya guardado, edítalo en su celda; para borrarlo, vacíalo.
        </p>
    </div>

    @if (empty($rows))
        <div class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No hay equipos configurados como
                <span class="font-semibold">{{ $isDaily ? 'Registro Diario' : 'Registro Semanal' }}</span>.
                Marca la «Frecuencia de lectura del horómetro» en la ficha de cada equipo.
            </p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-200">Equipo</th>
                        @foreach ($columns as $col)
                            <th class="px-2 py-2 text-center font-semibold text-gray-600 capitalize dark:text-gray-300">{{ $col['label'] }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-center font-semibold text-gray-700 dark:text-gray-200">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="sticky left-0 z-10 bg-white px-3 py-1.5 whitespace-nowrap dark:bg-gray-900">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $row['code'] }}</span>
                                <span class="text-gray-400">— {{ $row['name'] }}</span>
                            </td>

                            @foreach ($columns as $col)
                                @php $cell = $row['cells'][$col['key']]; @endphp
                                <td @class([
                                    'px-1.5 py-1 text-center align-middle',
                                    'bg-emerald-50 dark:bg-emerald-500/10' => $cell['filled'] && ! $cell['reset'],
                                    'bg-amber-50 dark:bg-amber-500/10' => $cell['filled'] && $cell['reset'],
                                ])>
                                    @if ($cell['filled'])
                                        {{-- Valor directo del servidor vía Alpine + wire:key único: al pasar de
                                             celda vacía a llena, Livewire no puede reutilizar el input vacío y dejar
                                             el número sin pintar (ese era el bug). --}}
                                        <input
                                            type="number"
                                            inputmode="decimal"
                                            step="0.1"
                                            min="0"
                                            wire:key="cell-{{ $cell['reading_id'] }}-{{ $cell['reading'] }}"
                                            x-data="{ v: @js((string) $cell['reading']) }"
                                            x-model="v"
                                            x-on:keydown.enter.prevent="$wire.saveEditedReading(@js($cell['reading_id']), v)"
                                            x-on:blur="$wire.saveEditedReading(@js($cell['reading_id']), v)"
                                            title="Corrige el valor y presiona Enter. Vacíalo para borrar la lectura."
                                            class="w-20 rounded-md border border-transparent bg-transparent px-1.5 py-0.5 text-center text-sm font-semibold text-gray-900 tabular-nums hover:border-gray-300 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500 dark:text-white dark:hover:border-white/20 dark:focus:bg-white/5"
                                        />
                                        <div class="text-[11px] text-gray-500 tabular-nums dark:text-gray-400">
                                            @if ($row['daily_hours'])
                                                h
                                            @elseif ($cell['baseline'])
                                                <span title="Primera lectura: la línea base. Las horas aparecen desde la siguiente." class="text-gray-400">1ª lectura</span>
                                            @else
                                                {{ number_format($cell['hours'], 0) }} h
                                            @endif
                                            @if ($cell['reset'])
                                                <span title="Cambio de dial (reset)" class="text-amber-600">⟳</span>
                                            @endif
                                        </div>
                                    @else
                                        <input
                                            type="number"
                                            inputmode="decimal"
                                            step="0.1"
                                            min="0"
                                            wire:key="empty-{{ $row['id'] }}-{{ $col['key'] }}"
                                            wire:model="draft.{{ $row['id'] }}.{{ $col['key'] }}"
                                            wire:keydown.enter="saveCell('{{ $row['id'] }}', '{{ $col['key'] }}')"
                                            wire:blur="saveCell('{{ $row['id'] }}', '{{ $col['key'] }}')"
                                            class="w-20 rounded-md border-gray-300 bg-white px-1.5 py-1 text-center text-sm tabular-nums focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                            placeholder="—"
                                        />
                                    @endif
                                </td>
                            @endforeach

                            <td class="px-3 py-1.5 text-center font-semibold text-gray-900 tabular-nums dark:text-white">
                                {{ number_format($row['total'], 0) }} h
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                        <td class="sticky left-0 z-10 bg-gray-50 px-3 py-2 font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">Total horas</td>
                        @foreach ($columns as $col)
                            <td class="px-2 py-2 text-center font-semibold text-gray-700 tabular-nums dark:text-gray-200">{{ number_format($columnTotals[$col['key']], 0) }}</td>
                        @endforeach
                        <td class="px-3 py-2 text-center font-bold text-emerald-700 tabular-nums dark:text-emerald-400">{{ number_format($grandTotal, 0) }} h</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
