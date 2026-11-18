{{--
    Una cuadrícula de doce meses, como las hojas del Excel de la Directora Administrativa:
    una fila por proveedor, cliente o concepto, los meses de enero a diciembre y el total.

    Cada fila: ['key' => …, 'label' => …, 'values' => [mes => cifra], 'call' => fn (int $mes): string
    con la llamada de Livewire que guarda la celda (o null si la fila solo se lee),
    'strong' => bool, 'tone' => fn (float $v): ?string, 'total' => cifra|null|false].

    Con $editable, las celdas con 'call' son campos: se guardan solos al salir de ellos.
--}}
@php
    $meses = \App\Filament\Pages\PlanAnual::MONTHS;
    $decimales ??= 2;
    $sufijo ??= '';
    $cifra = fn (?float $valor): string => $valor === null || abs($valor) < 0.005 ? '' : number_format($valor, $decimales, ',', '.').$sufijo;
    $conTotal ??= true;
@endphp

<div class="overflow-x-auto -mx-2 px-2">
    <table class="w-full text-sm border-separate border-spacing-0">
        <thead>
            <tr class="text-gray-600 dark:text-gray-300">
                <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-3 py-2 border-b border-gray-200 dark:border-white/10 min-w-44">{{ $encabezado ?? '' }}</th>
                @foreach ($meses as $mes)
                    <th class="text-right font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">{{ $mes }}</th>
                @endforeach
                @if ($conTotal)
                    <th class="text-right font-semibold px-3 py-2 border-b border-l border-gray-200 dark:border-white/10">Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                @php
                    $valores = $fila['values'] ?? [];
                    $total = array_key_exists('total', $fila) ? $fila['total'] : array_sum($valores);
                    $tono = $fila['tone'] ?? null;
                @endphp
                <tr wire:key="fila-{{ $clave ?? 'g' }}-{{ $fila['key'] }}" @class(['font-semibold bg-gray-50 dark:bg-white/5' => $fila['strong'] ?? false])>
                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-3 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">
                        {{ $fila['label'] }}
                        @if (! empty($fila['hint']))
                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $fila['hint'] }}</span>
                        @endif
                    </td>
                    @foreach ($meses as $numero => $mes)
                        @php $valor = $valores[$numero] ?? null; @endphp
                        <td @class([
                            'text-right tabular-nums px-1 py-1 border-b border-gray-100 dark:border-white/5',
                            'text-danger-600 dark:text-danger-400' => $tono && $valor !== null && $tono($valor) === 'danger',
                            'text-success-600 dark:text-success-400' => $tono && $valor !== null && $tono($valor) === 'success',
                        ])>
                            @if ($editable && ! empty($fila['call']))
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    value="{{ $valor === null ? '' : number_format($valor, $decimales, ',', '') }}"
                                    wire:change="{{ ($fila['call'])($numero) }}"
                                    aria-label="{{ $fila['label'] }} · {{ $mes }}"
                                    class="w-20 rounded-md border border-gray-200 bg-white px-1.5 py-1 text-right text-sm tabular-nums focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                                />
                            @else
                                <span class="px-1">{{ $cifra($valor) }}</span>
                            @endif
                        </td>
                    @endforeach
                    @if ($conTotal)
                        <td class="text-right tabular-nums font-semibold px-3 py-1.5 border-b border-l border-gray-100 dark:border-white/5">
                            {{ $total === false ? '' : $cifra($total) }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
