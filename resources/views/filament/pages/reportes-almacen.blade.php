@php
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $cifra = fn (float $valor): string => rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',');
    $tonos = ['A' => 'danger', 'B' => 'warning', 'C' => 'gray'];
@endphp

<x-filament-panels::page>
    {{-- El ABC en tres tarjetas: cuántos repuestos y cuánta plata en cada clase. --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach (['A' => 'Clase A · cada mes', 'B' => 'Clase B · cada trimestre', 'C' => 'Clase C · cada semestre'] as $clase => $rotulo)
            <x-fronda.kpi
                :label="$rotulo"
                :value="number_format($abcTotals[$clase]['parts'] ?? 0, 0, ',', '.').' repuestos'"
                :detail="$pesos((float) ($abcTotals[$clase]['value'] ?? 0)).' consumidos en el año'"
                icon="heroicon-o-squares-2x2"
            />
        @endforeach
    </div>

    <x-filament::section heading="ABC por consumo" description="De mayor a menor valor consumido. A hasta el 80 % del valor, B hasta el 95 %, C el resto. La rotación es cuántas veces salió su existencia actual en el año.">
        @if ($abc->isEmpty())
            <x-fronda.empty-state icon="heroicon-o-chart-bar" heading="Todavía no hay repuestos">Cuando el almacén tenga repuestos y movimientos, aquí aparece su clasificación.</x-fronda.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pe-3">Repuesto</th>
                            <th class="py-2 pe-3 text-end">Consumido</th>
                            <th class="py-2 pe-3 text-end">% acumulado</th>
                            <th class="py-2 pe-3 text-end">Existencia</th>
                            <th class="py-2 pe-3 text-end">Rotación</th>
                            <th class="py-2 pe-3">Clase</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($abc->take(60) as $fila)
                            <tr class="border-t border-gray-100 dark:border-white/5">
                                <td class="py-2 pe-3">
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $fila['name'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['code'] }}</p>
                                </td>
                                <td class="py-2 pe-3 text-end tabular-nums">{{ $pesos($fila['value']) }}</td>
                                <td class="py-2 pe-3 text-end tabular-nums">{{ number_format($fila['cumulative'], 1, ',', '.') }} %</td>
                                <td class="py-2 pe-3 text-end tabular-nums">{{ $cifra($fila['stock']) }}</td>
                                <td class="py-2 pe-3 text-end tabular-nums">{{ $fila['turns'] === null ? '—' : number_format($fila['turns'], 1, ',', '.').' veces' }}</td>
                                <td class="py-2 pe-3">
                                    <x-filament::badge :color="$tonos[$fila['proposed']->value]">{{ $fila['proposed']->value }}</x-filament::badge>
                                    @if ($fila['current'] !== $fila['proposed'])
                                        <span class="text-xs text-gray-500 dark:text-gray-400">hoy {{ $fila['current']?->value ?? 'sin clase' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Plata quieta" description="Repuestos con existencia y sin una sola salida en los últimos seis meses.">
            @forelse ($idle->take(15) as $fila)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $fila['name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['code'] }} · {{ $cifra($fila['stock']) }} en existencia · {{ $fila['lastOut'] ? 'última salida '.$fila['lastOut']->format('d/m/Y') : 'nunca ha salido' }}</p>
                    </div>
                    <p class="shrink-0 text-sm font-bold tabular-nums">{{ $pesos($fila['value']) }}</p>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Nada quieto: todo lo que hay ha tenido movimiento.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Consumo por equipo" description="Lo despachado a órdenes de trabajo en el último año.">
            @forelse ($byEquipment->take(15) as $fila)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $fila['equipment'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['movements'] }} {{ $fila['movements'] === 1 ? 'despacho' : 'despachos' }}</p>
                    </div>
                    <p class="shrink-0 text-sm font-bold tabular-nums">{{ $pesos($fila['value']) }}</p>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Todavía no se ha despachado nada a órdenes de trabajo.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Salidas sin orden, por motivo" description="Lo que salió con vale en el último año.">
            @forelse ($reasons as $fila)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $fila['reason'] }} <span class="text-xs font-normal text-gray-500">· {{ $fila['movements'] }}</span></p>
                    <p class="shrink-0 text-sm font-bold tabular-nums">{{ $pesos($fila['value']) }}</p>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">No hay salidas sin orden de trabajo en el año.</p>
            @endforelse
        </x-filament::section>

        <x-filament::section heading="Quiénes más reciben" description="Los diez que más se han llevado con vale de salida.">
            @forelse ($recipients as $fila)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $fila['name'] }} <span class="text-xs font-normal text-gray-500">· {{ $fila['movements'] }}</span></p>
                    <p class="shrink-0 text-sm font-bold tabular-nums">{{ $pesos($fila['value']) }}</p>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Todavía nadie ha recibido con vale.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
