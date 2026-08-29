@php
    $guion = '<span class="text-gray-300 dark:text-gray-600">—</span>';
    $num = fn (?float $v, int $dec = 0) => $v === null ? null : number_format($v, $dec, ',', '.');
@endphp

{{--
    Un mes por fila.

    Antes iba al revés, con los doce meses en columnas como la hoja de la planta. Esa
    forma se conservó a propósito y dejó de servir en cuanto la pantalla no la pudo
    mostrar entera: había que desplazarse a lo ancho para leer octubre.
--}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Energía · {{ $year }}
        </x-slot>

        <x-slot name="description">
            Un mes sin dato muestra «—». Pulsa un mes para ver sus días.
        </x-slot>

        @if ($canEdit)
            <x-slot name="afterHeader">
                <div class="flex flex-wrap items-center gap-2">
                    {{ $this->editMonthAction }}
                    {{ $this->recalculateMonthAction }}
                </div>
            </x-slot>
        @endif

        @if ($empty)
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay ninguna planta registrada.</p>
        @else
            <div class="overflow-x-auto -mx-2 px-2">
                <table class="w-full text-sm border-separate border-spacing-0">
                    <thead>
                        <tr class="text-gray-600 dark:text-gray-300">
                            <th class="text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">MES</th>
                            <th class="text-right font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">RFF (t)</th>
                            <th class="text-right font-bold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap text-gray-900 dark:text-white">KWh/RFF</th>
                            <th class="text-right font-bold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap text-gray-900 dark:text-white">KWh TOTAL</th>
                            <th class="text-right font-semibold px-3 py-2 border-b border-l border-gray-200 dark:border-white/10 whitespace-nowrap">RED</th>
                            <th class="text-right font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">PLANTA</th>
                            <th class="text-right font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap">TURBINA</th>
                            <th class="text-right font-semibold px-3 py-2 border-b border-l border-gray-200 dark:border-white/10 whitespace-nowrap">LIMPIA</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rows as $numero => $fila)
                            <tr wire:key="mes-{{ $numero }}"
                                wire:click="toggleMonth({{ $numero }})"
                                @class([
                                    'cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5',
                                    'bg-gray-50 dark:bg-white/5' => $openMonth === $numero,
                                ])
                            >
                                <th class="text-left px-3 py-2 border-b border-gray-100 dark:border-white/5 whitespace-nowrap font-medium">
                                    <span class="inline-block w-3 text-gray-400">{{ $openMonth === $numero ? '▾' : '▸' }}</span>
                                    {{ $months[$numero] }}
                                    @if ($fila['is_manual'])
                                        <span class="text-primary-600 dark:text-primary-400" title="Corregido a mano">•</span>
                                    @endif
                                </th>

                                <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap">
                                    {!! $num($fila['processed_tons']) ?? $guion !!}
                                </td>
                                <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap font-bold">
                                    {!! $num($fila['kwh_per_ton'], 2) ?? $guion !!}
                                </td>
                                <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap font-bold">
                                    {!! $num($fila['kwh_total']) ?? $guion !!}
                                </td>
                                <td class="text-right px-3 py-2 border-b border-l border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {!! $num($fila['kwh_grid']) ?? $guion !!}
                                </td>
                                <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {!! $num($fila['kwh_genset']) ?? $guion !!}
                                </td>
                                <td class="text-right px-3 py-2 border-b border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap text-gray-500 dark:text-gray-400">
                                    {!! $num($fila['kwh_turbine']) ?? $guion !!}
                                </td>
                                <td class="text-right px-3 py-2 border-b border-l border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap">
                                    {!! $num($fila['clean_energy_percentage'], 2) ?? $guion !!}
                                </td>
                            </tr>

                            {{-- El detalle del mes, dentro de su propia fila. De solo
                                 lectura: corregir sigue siendo un único camino, la ronda
                                 de «Energía», que es donde vive el aviso del dígito de más. --}}
                            @if ($openMonth === $numero)
                                <tr wire:key="detalle-{{ $numero }}">
                                    <td colspan="8" class="px-3 py-3 border-b border-gray-100 dark:border-white/5 bg-gray-50/60 dark:bg-white/[0.03]">
                                        @if ($dailyDetail === null || $dailyDetail['meters']->isEmpty())
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Esta planta no tiene contadores configurados.</p>
                                        @elseif (! $dailyDetail['has_readings'])
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $months[$numero] }} todavía no tiene ningún día registrado.
                                            </p>
                                        @else
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-xs border-separate border-spacing-0">
                                                    <thead>
                                                        <tr class="text-gray-500 dark:text-gray-400">
                                                            <th rowspan="2" class="text-left font-semibold px-2 py-1 whitespace-nowrap align-bottom">DÍA</th>
                                                            @foreach ($dailyDetail['meters'] as $meter)
                                                                <th colspan="2" class="text-center font-semibold px-2 py-1 border-l border-gray-200 dark:border-white/10 whitespace-nowrap">
                                                                    {{ mb_strtoupper($meter->source->label()) }}
                                                                </th>
                                                            @endforeach
                                                        </tr>
                                                        <tr class="text-gray-400 dark:text-gray-500">
                                                            @foreach ($dailyDetail['meters'] as $meter)
                                                                <th class="text-right font-normal px-2 py-1 border-l border-gray-200 dark:border-white/10 whitespace-nowrap">acum.</th>
                                                                <th class="text-right font-normal px-2 py-1 whitespace-nowrap">consumo</th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($dailyDetail['days'] as $day)
                                                            <tr>
                                                                <th class="text-left px-2 py-1 whitespace-nowrap font-medium">{{ ucfirst($day['label']) }}</th>
                                                                @foreach ($dailyDetail['meters'] as $meter)
                                                                    @php($celda = $day['cells'][$meter->id])
                                                                    <td class="text-right px-2 py-1 border-l border-gray-100 dark:border-white/5 tabular-nums whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                                        {!! $num($celda['accumulated']) ?? $guion !!}
                                                                    </td>
                                                                    <td class="text-right px-2 py-1 tabular-nums whitespace-nowrap">
                                                                        {!! $num($celda['delta']) ?? $guion !!}
                                                                        @if ($celda['is_reset'])
                                                                            <span class="text-warning-600 dark:text-warning-400" title="Contador reemplazado">↺</span>
                                                                        @endif
                                                                    </td>
                                                                @endforeach
                                                            </tr>
                                                        @endforeach
                                                        <tr class="font-bold">
                                                            <th class="text-left px-2 py-1 whitespace-nowrap">TOTAL</th>
                                                            @foreach ($dailyDetail['meters'] as $meter)
                                                                <td class="border-l border-gray-100 dark:border-white/5"></td>
                                                                <td class="text-right px-2 py-1 tabular-nums whitespace-nowrap">
                                                                    {{ number_format($dailyDetail['totals'][$meter->id] ?? 0, 0, ',', '.') }}
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach

                        <tr class="bg-gray-100 dark:bg-white/10">
                            <th class="text-left px-3 py-2 whitespace-nowrap font-bold">AÑO</th>
                            <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">{!! $num($totals['processed_tons']) ?? $guion !!}</td>
                            <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">{!! $num($totals['kwh_per_ton'], 2) ?? $guion !!}</td>
                            <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">{!! $num($totals['kwh_total']) ?? $guion !!}</td>
                            <td class="text-right px-3 py-2 border-l border-gray-200 dark:border-white/10 tabular-nums whitespace-nowrap font-bold">{!! $num($totals['kwh_grid']) ?? $guion !!}</td>
                            <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">{!! $num($totals['kwh_genset']) ?? $guion !!}</td>
                            <td class="text-right px-3 py-2 tabular-nums whitespace-nowrap font-bold">{!! $num($totals['kwh_turbine']) ?? $guion !!}</td>
                            <td class="text-right px-3 py-2 border-l border-gray-200 dark:border-white/10 tabular-nums whitespace-nowrap font-bold">{!! $num($totals['clean_energy_percentage'], 2) ?? $guion !!}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        <x-filament-actions::modals />
    </x-filament::section>
</x-filament-widgets::widget>
