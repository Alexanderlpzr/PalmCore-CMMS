@php
    $runs = $this->runs();
    $informe = $this->report();
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $horas = fn (float $valor): string => rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
@endphp

<x-filament-panels::page>
    @if ($runs->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Todavía no hay nóminas liquidadas. El indicador sale de la nómina: liquide una en «Nóminas» y vuelva aquí.
            </p>
        </x-filament::section>
    @else
        <div class="max-w-md">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="runId" aria-label="Nómina">
                    @foreach ($runs as $id => $nombre)
                        <option value="{{ $id }}">{{ $nombre }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        @if ($informe)
            @php
                $run = $informe['run'];
                $anterior = $informe['previous']['groups'] ?? [];
                $totalAnterior = $informe['previous']['total'] ?? null;
            @endphp

            {{--
                El informe TH-INF-F-002, con las cifras de la nómina en vez de tecleadas. Las
                horas pagadas como bonificación cuentan aquí con su clase: para el indicador
                importa lo que costaron, no el concepto con que se pagaron.
            --}}
            <x-filament::section>
                <x-slot name="heading">
                    Recargos y horas extras por grupo
                </x-slot>

                <x-slot name="description">
                    Horas del {{ $run->hoursFrom()->format('d/m/Y') }} al {{ $run->hoursTo()->format('d/m/Y') }}.
                    Los recargos pagan solo el recargo, porque la hora ya va en el sueldo; las extras pagan la hora completa. El factor es cuánto se pagó en recargos y extras por cada peso ordinario.
                </x-slot>

                <div class="overflow-x-auto -mx-2 px-2">
                    <table class="w-full text-sm border-separate border-spacing-0">
                        <thead>
                            <tr class="text-gray-600 dark:text-gray-300">
                                <th rowspan="2" class="text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 align-bottom">Grupo</th>
                                <th rowspan="2" class="text-right font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 align-bottom">Ordinario</th>
                                <th colspan="2" class="text-center font-semibold px-3 py-1 border-b border-l border-gray-200 dark:border-white/10">Recargos</th>
                                <th colspan="2" class="text-center font-semibold px-3 py-1 border-b border-l border-gray-200 dark:border-white/10">Extras</th>
                                <th colspan="2" class="text-center font-semibold px-3 py-1 border-b border-l border-gray-200 dark:border-white/10">Recargos y extras</th>
                                <th rowspan="2" class="text-right font-semibold px-3 py-2 border-b border-l border-gray-200 dark:border-white/10 align-bottom">Factor</th>
                                <th colspan="2" class="text-center font-semibold px-3 py-1 border-b border-l border-gray-200 dark:border-white/10">
                                    {{ $informe['previousRun']?->name ?? 'Mes anterior' }}
                                </th>
                            </tr>
                            <tr class="text-xs text-gray-500 dark:text-gray-400">
                                @foreach (['Horas', 'Valor', 'Horas', 'Valor', 'Horas', 'Valor'] as $i => $sub)
                                    <th class="text-right font-medium px-3 py-1 border-b {{ $i % 2 === 0 ? 'border-l' : '' }} border-gray-200 dark:border-white/10">{{ $sub }}</th>
                                @endforeach
                                <th class="text-right font-medium px-3 py-1 border-b border-l border-gray-200 dark:border-white/10">Valor</th>
                                <th class="text-right font-medium px-3 py-1 border-b border-gray-200 dark:border-white/10">Variación</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([...$informe['current']['groups'], 'total' => $informe['current']['total']] as $clave => $fila)
                                @php
                                    $previa = $clave === 'total' ? $totalAnterior : ($anterior[$clave] ?? null);
                                    $variacion = $previa && $previa['amount'] > 0 ? ($fila['amount'] - $previa['amount']) / $previa['amount'] : null;
                                    $esTotal = $clave === 'total';
                                @endphp
                                <tr @class(['font-semibold bg-gray-50 dark:bg-white/5' => $esTotal])>
                                    <td class="px-3 py-2 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">
                                        {{ $fila['label'] }}
                                        @unless ($esTotal)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">· {{ $fila['workers'] }}</span>
                                        @endunless
                                    </td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-gray-100 dark:border-white/5">{{ $fila['ordinary'] > 0 ? $pesos($fila['ordinary']) : '—' }}</td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-l border-gray-100 dark:border-white/5">{{ $horas($fila['surcharge_hours']) }}</td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-gray-100 dark:border-white/5">{{ $pesos($fila['surcharge_amount']) }}</td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-l border-gray-100 dark:border-white/5">{{ $horas($fila['overtime_hours']) }}</td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-gray-100 dark:border-white/5">{{ $pesos($fila['overtime_amount']) }}</td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-l border-gray-100 dark:border-white/5">{{ $horas($fila['hours']) }}</td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-gray-100 dark:border-white/5">{{ $pesos($fila['amount']) }}</td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-l border-gray-100 dark:border-white/5">
                                        {{ $fila['factor'] === null ? '—' : number_format($fila['factor'] * 100, 1, ',', '.').' %' }}
                                    </td>
                                    <td class="text-right tabular-nums px-3 py-2 border-b border-l border-gray-100 dark:border-white/5">{{ $previa ? $pesos($previa['amount']) : '—' }}</td>
                                    <td @class([
                                        'text-right tabular-nums px-3 py-2 border-b border-gray-100 dark:border-white/5',
                                        'text-danger-600 dark:text-danger-400' => $variacion !== null && $variacion > 0,
                                        'text-success-600 dark:text-success-400' => $variacion !== null && $variacion < 0,
                                    ])>
                                        {{ $variacion === null ? '—' : ($variacion > 0 ? '+' : '').number_format($variacion * 100, 1, ',', '.').' %' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    «Apoyo a mantenimiento» son las horas de producción en los días marcados así en «Horas por confirmar»: salen de Producción y su sueldo se queda allá.
                </p>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
