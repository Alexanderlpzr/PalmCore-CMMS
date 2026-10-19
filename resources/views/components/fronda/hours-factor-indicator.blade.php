@props(['factor'])

{{--
    El indicador «factor de horas» con los renglones del Excel de talento humano
    (TH-INF-F-002), de lo que va del corte. Lo arma `FactorDeHoras::dashboardIndicator()`,
    que solo lo entrega a quien ve la nómina: trae sueldos.
--}}
@php
    $pesos = fn (float $v): string => '$ '.number_format($v, 0, ',', '.');
    $horasEnteras = fn (float $h): string => rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',');
    // Los colores de grupo de los demás tableros: producción verde, mantenimiento petróleo, MOPE ámbar.
    $marca = fn (string $label): string => match (true) {
        str_contains($label, 'MOPE') => \App\Domain\HumanResources\Services\LaborCostReport::AREA_COLORS['mope'],
        str_contains($label, 'mantenimiento') => \App\Support\FrondaPalette::Petrol[600],
        default => \App\Support\FrondaPalette::Brand[600],
    };
@endphp

<x-filament::section>
    <x-slot name="heading">
        Indicador factor de horas · corte al {{ $factor['until']->format('d/m/Y') }}
    </x-slot>

    <x-slot name="description">
        Como el TH-INF-F-002 de Talento Humano: del {{ $factor['from']->format('d/m/Y') }} a hoy. Lo ordinario es el sueldo del mes de cada grupo, con las horas ordinarias trabajadas en el corte; «con recargos» suma recargos y horas extras, y «sin recargos» son solo las horas extras.
        @if ($factor['estimated'])
            <span class="font-medium text-warning-600 dark:text-warning-400">Estimado: incluye horas sin confirmar.</span>
        @endif
    </x-slot>

    <x-slot name="afterHeader">
        <x-filament::link :href="$factor['url']" icon="heroicon-m-arrow-right" icon-position="after" size="sm">
            Ver el detalle
        </x-filament::link>
    </x-slot>

    <div class="-mx-2 overflow-x-auto px-2">
        <table class="w-full min-w-[34rem] border-separate border-spacing-0 text-sm">
            <thead>
                <tr class="text-gray-600 dark:text-gray-300">
                    <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Detalle de horas</th>
                    <th class="w-24 border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Horas</th>
                    <th class="w-40 border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($factor['ordinary'] as $fila)
                    <tr>
                        <td class="border-b border-gray-100 px-3 py-2 dark:border-white/5">
                            <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-black/5 dark:ring-white/50" style="background-color: {{ $marca($fila['label']) }}"></span>{{ $fila['label'] }}</span>
                        </td>
                        <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $fila['hours'] > 0 ? $horasEnteras($fila['hours']) : '—' }}</td>
                        <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $fila['amount'] > 0 ? $pesos($fila['amount']) : '—' }}</td>
                    </tr>
                @endforeach

                @foreach ($factor['withSurcharges'] as $fila)
                    <tr>
                        <td class="border-b border-gray-100 px-3 py-2 dark:border-white/5">
                            <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-black/5 dark:ring-white/50" style="background-color: {{ $marca($fila['label']) }}"></span>{{ $fila['label'] }}</span>
                        </td>
                        <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $horasEnteras($fila['hours']) }}</td>
                        <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $fila['amount'] > 0 ? $pesos($fila['amount']) : '—' }}</td>
                    </tr>
                @endforeach

                <tr class="bg-gray-50 font-semibold dark:bg-white/5">
                    <td class="border-b border-gray-200 px-3 py-2 dark:border-white/10">Total nómina de personal de producción y mantenimiento</td>
                    <td class="border-b border-gray-200 px-3 py-2 text-right tabular-nums dark:border-white/10">{{ $horasEnteras($factor['payrollTotal']['hours']) }}</td>
                    <td class="border-b border-gray-200 px-3 py-2 text-right tabular-nums dark:border-white/10">{{ $pesos($factor['payrollTotal']['amount']) }}</td>
                </tr>

                <tr><td colspan="3" class="h-4"></td></tr>

                @foreach ($factor['overtime'] as $fila)
                    <tr>
                        <td class="border-b border-gray-100 px-3 py-2 dark:border-white/5">
                            <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-black/5 dark:ring-white/50" style="background-color: {{ $marca($fila['label']) }}"></span>{{ $fila['label'] }}</span>
                        </td>
                        <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $horasEnteras($fila['hours']) }}</td>
                        <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $fila['amount'] > 0 ? $pesos($fila['amount']) : '—' }}</td>
                    </tr>
                @endforeach

                <tr class="bg-gray-50 font-semibold dark:bg-white/5">
                    <td class="px-3 py-2">Total horas extras de producción y mantenimiento sin recargos</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $horasEnteras($factor['overtimeTotal']['hours']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ $pesos($factor['overtimeTotal']['amount']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Los totales de horas son solo los de recargos y extras, como en el Excel. Desde el corte de octubre de 2026, MOPE va dentro de Mantenimiento.
    </p>
</x-filament::section>
