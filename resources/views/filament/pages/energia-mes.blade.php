@php($tabla = $this->monthTable())
@php($hayLecturas = collect($tabla['days'])->contains(
    fn (array $day): bool => collect($day['cells'])->contains(fn (array $c): bool => $c['accumulated'] !== null)
))

{{--
    La mitad de abajo de la hoja que este módulo reemplazó: una fila por día con el
    acumulado y el consumo de cada contador.

    Las fechas van en filas y los contadores en columnas, como en su hoja. Al revés
    —contadores en filas— serían tres filas por treinta y una columnas, ilegible.
--}}
<x-filament::section>
    <x-slot name="heading">
        Lecturas de {{ $tabla['monthLabel'] }}
    </x-slot>

    <x-slot name="description">
        Un día sin leer muestra «—», no cero. Pulsa una fila para llevar la ronda de arriba a ese día.
    </x-slot>

    @if ($tabla['meters']->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Esta planta no tiene contadores configurados.
        </p>
    {{-- El mes en curso genera sus días hasta hoy aunque ninguno tenga lectura, así que
         mirar solo si la lista está vacía dejaba la tabla pintando treinta filas de
         guiones. Lo que importa es si hay alguna lectura, no si hay días. --}}
    @elseif (! $hayLecturas)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $tabla['monthLabel'] }} todavía no tiene ninguna lectura anotada.
        </p>
    @else
        <div class="overflow-x-auto -mx-2 px-2">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr>
                        <th rowspan="2" class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap align-bottom">
                            FECHA
                        </th>
                        @foreach ($tabla['meters'] as $meter)
                            <th colspan="2" class="text-center font-semibold px-3 py-1 border-b border-l border-gray-200 dark:border-white/10 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ mb_strtoupper($meter->source->label()) }}
                            </th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($tabla['meters'] as $meter)
                            <th class="text-right font-normal px-3 py-1 border-b border-l border-gray-200 dark:border-white/10 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                acumulado
                            </th>
                            <th class="text-right font-normal px-3 py-1 border-b border-gray-200 dark:border-white/10 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                consumo
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($tabla['days'] as $day)
                        <tr wire:key="dia-{{ $day['date'] }}"
                            wire:click="goToDay('{{ $day['date'] }}')"
                            @class([
                                'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5',
                                'bg-primary-50 dark:bg-primary-500/10' => $day['date'] === ($this->data['reading_date'] ?? null),
                            ])
                        >
                            <th class="sticky left-0 z-10 bg-inherit text-left px-3 py-2 border-b border-gray-100 dark:border-white/5 whitespace-nowrap font-medium">
                                {{ ucfirst($day['label']) }}
                            </th>

                            @foreach ($tabla['meters'] as $meter)
                                @php($celda = $day['cells'][$meter->id])

                                <td class="text-right px-3 py-2 border-b border-l border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    @if ($celda['accumulated'] === null)
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @else
                                        {{ number_format($celda['accumulated'], 0, ',', '.') }}
                                    @endif
                                </td>

                                <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap font-medium">
                                    @if ($celda['delta'] === null)
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @else
                                        {{ number_format($celda['delta'], 0, ',', '.') }}
                                        {{-- Un contador reemplazado arranca casi de cero: sin
                                             marcarlo, su consumo parece un error de captura. --}}
                                        @if ($celda['is_reset'])
                                            <span class="text-warning-600 dark:text-warning-400"
                                                  title="Contador reemplazado">↺</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-900 text-left px-3 py-2 whitespace-nowrap font-bold">
                            TOTAL DEL MES
                        </th>
                        @foreach ($tabla['meters'] as $meter)
                            <td class="border-l border-gray-100 dark:border-white/5"></td>
                            <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">
                                {{ number_format($tabla['totals'][$meter->id] ?? 0, 0, ',', '.') }}
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>
