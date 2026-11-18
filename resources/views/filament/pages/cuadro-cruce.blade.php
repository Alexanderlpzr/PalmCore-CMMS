@php
    $plan = $this->plan();
    $datos = $this->data();
    $clientes = $this->customers();
    $proveedores = $this->suppliers();
    $meses = \App\Filament\Pages\PlanAnual::MONTHS;
    $tn = fn (?float $v, int $d = 2): string => $v === null ? '—' : number_format($v, $d, ',', '.');
    $pesos = fn (float $v): string => '$'.number_format($v, 0, ',', '.');
    $millones = fn (float $v): string => '$'.number_format($v / 1_000_000, 0, ',', '.').' M';
    $pct = fn (?float $r): string => $r === null ? '—' : number_format($r * 100, 1, ',', '.').' %';
    $nombre = fn (string $id): string => $id === \App\Domain\Commercial\Services\AnnualPlanService::UNASSIGNED ? 'Por concretar' : ($clientes[$id]['name'] ?? '—');
    $cpo = $datos['products']['cpo'];
    $pko = $datos['products']['pko'];
    $sinAsignar = \App\Domain\Commercial\Services\AnnualPlanService::UNASSIGNED;
    // Lo que está «por concretar» no tiene cliente: no cuenta para el cumplimiento, como en el Excel.
    $suma = fn (array $filas, string $campo): float => round(array_sum(array_column(array_diff_key($filas, [$sinAsignar => true]), $campo)), 2);
    $porConcretarCpo = (float) ($cpo['rows'][$sinAsignar]['projected'] ?? 0);
    $proyectadoCpo = $suma($cpo['rows'], 'projected');
    $despachadoCpo = $suma($cpo['rows'], 'dispatched');
    $transitoCpo = $suma($cpo['rows'], 'in_transit');
    $facturado = $suma($cpo['rows'], 'invoiced') + $suma($pko['rows'], 'invoiced');
    $pagado = $suma($cpo['rows'], 'paid') + $suma($pko['rows'], 'paid');
    $cartera = $facturado - $pagado;
    $frutaPlan = array_sum($datos['fruit_plan_months']);
    $frutaReal = array_sum($datos['fruit_real_months']);
    $despachosUrl = \App\Filament\Resources\Commercial\SalesDispatch\SalesDispatchResource::getUrl('index');
    $colorCumplimiento = fn (?float $r): string => match (true) { $r === null => '', $r >= 0.95 => 'text-success-600 dark:text-success-400', $r >= 0.8 => 'text-warning-600 dark:text-warning-400', default => 'text-danger-600 dark:text-danger-400' };
    $celdaTh = 'text-right font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap';
    $celda = 'text-right tabular-nums px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap';
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::input.wrapper class="w-32">
            <x-filament::input type="number" wire:model.live.debounce.500ms="year" min="2020" max="2100" aria-label="Año" />
        </x-filament::input.wrapper>

        <x-filament::tabs>
            <x-filament::tabs.item :active="$this->against === 'reproyeccion'" wire:click="$set('against', 'reproyeccion')" icon="heroicon-m-pencil-square">
                Contra la reproyección
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$this->against === 'original'" wire:click="$set('against', 'original')" icon="heroicon-m-lock-closed">
                Contra el plan original
            </x-filament::tabs.item>
        </x-filament::tabs>

        <span class="text-sm text-gray-500 dark:text-gray-400">
            @if ($datos['last_loaded_on'])
                Despachos registrados hasta el {{ \Carbon\CarbonImmutable::parse($datos['last_loaded_on'])->format('d/m/Y') }}.
            @endif
            @if (! $plan)
                Sin plan para {{ $this->year }}: créelo en «Plan anual» para ver lo proyectado.
            @endif
        </span>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-fronda.kpi label="Aceite proyectado" :value="$tn($proyectadoCpo).' tn'" icon="heroicon-o-calendar-days"
            :detail="'Despachado '.$tn($despachadoCpo).' tn · '.$pct($proyectadoCpo > 0 ? $despachadoCpo / $proyectadoCpo : null)"
            :percent="$proyectadoCpo > 0 ? min(100, $despachadoCpo / $proyectadoCpo * 100) : null" color="#1A7E42" />
        <x-fronda.kpi label="Pendiente por despachar" :value="$tn(max($proyectadoCpo - $despachadoCpo - $transitoCpo, 0)).' tn'" icon="heroicon-o-truck"
            :detail="($transitoCpo > 0 ? $tn($transitoCpo).' tn van en camino' : 'Nada en camino').($porConcretarCpo > 0 ? ' · '.$tn($porConcretarCpo).' tn más por concretar' : '')" :href="$despachosUrl.'?tab=cargado'" />
        <x-fronda.kpi label="Fruta recibida" :value="$tn($frutaReal).' tn'" icon="heroicon-o-scale"
            :detail="'De '.$tn($frutaPlan).' tn proyectadas · '.$pct($frutaPlan > 0 ? $frutaReal / $frutaPlan : null)"
            :percent="$frutaPlan > 0 ? min(100, $frutaReal / $frutaPlan * 100) : null" color="#00384C" />
        <x-fronda.kpi label="Por cobrar" :value="$millones($cartera)" icon="heroicon-o-banknotes" :tone="$cartera > 0 ? 'danger' : 'ok'"
            :detail="'Facturado '.$millones($facturado).' · pagado '.$millones($pagado)" :href="$despachosUrl.'?tab=facturado'" />
    </div>

    {{-- Avance por cliente: el «Resumen anual por cliente» del Cuadro Cruce. --}}
    @foreach (['cpo' => 'Aceite de palma (CPO)', 'pko' => 'Palmiste (PKO)'] as $clave => $titulo)
        @php $producto = $datos['products'][$clave]; @endphp
        @continue($clave === 'pko' && $producto['rows'] === [])
        <x-filament::section>
            <x-slot name="heading">Avance por cliente · {{ $titulo }}</x-slot>
            <x-slot name="description">Proyectado del año contra lo que recibió cada cliente (peso de llegada), lo que va en camino y cómo va su facturación.</x-slot>
            <div class="overflow-x-auto -mx-2 px-2">
                <table class="w-full text-sm border-separate border-spacing-0">
                    <thead>
                        <tr class="text-gray-600 dark:text-gray-300">
                            <th class="text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Cliente</th>
                            <th class="text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Destino</th>
                            <th class="{{ $celdaTh }}">Proyectado (tn)</th>
                            <th class="{{ $celdaTh }}">Despachado (tn)</th>
                            <th class="{{ $celdaTh }}">En camino (tn)</th>
                            <th class="{{ $celdaTh }}">Pendiente (tn)</th>
                            <th class="{{ $celdaTh }}">% cumpl.</th>
                            <th class="{{ $celdaTh }} border-l">Sin facturar (tn)</th>
                            <th class="{{ $celdaTh }}">Facturado</th>
                            <th class="{{ $celdaTh }}">Pagado</th>
                            <th class="{{ $celdaTh }}">Por cobrar</th>
                            <th class="text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Facturación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($producto['rows'])->except($sinAsignar)->sortByDesc('projected') as $id => $fila)
                            <tr wire:key="avance-{{ $clave }}-{{ $id }}">
                                <td class="px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap font-medium">{{ $nombre($id) }}</td>
                                <td class="px-2 py-1.5 border-b border-gray-100 dark:border-white/5 text-gray-500 dark:text-gray-400">{{ $clientes[$id]['market'] ?? '' }}</td>
                                <td class="{{ $celda }}">{{ $tn($fila['projected']) }}</td>
                                <td class="{{ $celda }}">{{ $tn($fila['dispatched']) }}</td>
                                <td class="{{ $celda }}">{{ $fila['in_transit'] > 0 ? $tn($fila['in_transit']) : '' }}</td>
                                <td class="{{ $celda }}">{{ $fila['pending'] > 0 ? $tn($fila['pending']) : '' }}</td>
                                <td class="{{ $celda }} font-semibold {{ $colorCumplimiento($fila['ratio']) }}">{{ $pct($fila['ratio']) }}</td>
                                <td class="{{ $celda }} border-l">{{ $fila['uninvoiced_tons'] > 0 ? $tn($fila['uninvoiced_tons']) : '' }}</td>
                                <td class="{{ $celda }}">{{ $fila['invoiced'] > 0 ? $pesos($fila['invoiced']) : '' }}</td>
                                <td class="{{ $celda }}">{{ $fila['paid'] > 0 ? $pesos($fila['paid']) : '' }}</td>
                                <td @class([$celda, 'font-semibold text-danger-600 dark:text-danger-400' => $fila['receivable'] > 1])>{{ $fila['receivable'] > 1 ? $pesos($fila['receivable']) : '' }}</td>
                                <td class="px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $id === \App\Domain\Commercial\Services\AnnualPlanService::UNASSIGNED ? '' : \App\Filament\Pages\CuadroCruce::billingState($fila) }}</td>
                            </tr>
                        @endforeach
                        @php
                            $totales = collect(['projected', 'dispatched', 'in_transit', 'pending', 'uninvoiced_tons', 'invoiced', 'paid', 'receivable'])->mapWithKeys(fn ($c) => [$c => $suma($producto['rows'], $c)]);
                        @endphp
                        <tr class="font-semibold bg-gray-50 dark:bg-white/5">
                            <td class="px-2 py-2" colspan="2">Total</td>
                            <td class="{{ $celda }}">{{ $tn($totales['projected']) }}</td>
                            <td class="{{ $celda }}">{{ $tn($totales['dispatched']) }}</td>
                            <td class="{{ $celda }}">{{ $tn($totales['in_transit']) }}</td>
                            <td class="{{ $celda }}">{{ $tn($totales['pending']) }}</td>
                            <td class="{{ $celda }} {{ $colorCumplimiento($totales['projected'] > 0 ? $totales['dispatched'] / $totales['projected'] : null) }}">{{ $pct($totales['projected'] > 0 ? $totales['dispatched'] / $totales['projected'] : null) }}</td>
                            <td class="{{ $celda }} border-l">{{ $tn($totales['uninvoiced_tons']) }}</td>
                            <td class="{{ $celda }}">{{ $pesos($totales['invoiced']) }}</td>
                            <td class="{{ $celda }}">{{ $pesos($totales['paid']) }}</td>
                            <td class="{{ $celda }} text-danger-600 dark:text-danger-400">{{ $pesos($totales['receivable']) }}</td>
                            <td></td>
                        </tr>
                        @if (($producto['rows'][$sinAsignar]['projected'] ?? 0) > 0)
                            <tr class="text-gray-500 dark:text-gray-400">
                                <td class="px-2 py-1.5" colspan="2">Por concretar <span class="text-xs">(sin cliente; no cuenta en el cumplimiento)</span></td>
                                <td class="{{ $celda }}">{{ $tn($producto['rows'][$sinAsignar]['projected']) }}</td>
                                <td colspan="9"></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach

    {{-- Mes a mes: despachado sobre proyectado, cliente por cliente. --}}
    <x-filament::section>
        <x-slot name="heading">Aceite mes a mes: despachado / proyectado (tn)</x-slot>
        <x-slot name="description">Cada celda dice lo que recibió el cliente ese mes (por mes de cargue) y, debajo, lo proyectado. En rojo los meses atrasados, en verde los cumplidos. Un mes puede ir atrasado y el siguiente adelantado aunque el año vaya bien.</x-slot>
        <div class="overflow-x-auto -mx-2 px-2">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Cliente</th>
                        @foreach ($meses as $mes)
                            <th class="{{ $celdaTh }}">{{ $mes }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        $filasMes = collect($cpo['rows'])->sortByDesc('projected')->map(fn ($f, $id) => ['label' => $nombre($id), 'real' => $f['dispatched_months'], 'plan' => $f['projected_months']])->all();
                        $filasMes['total'] = ['label' => 'Total', 'real' => $cpo['dispatched_months'], 'plan' => $cpo['projected_months'], 'strong' => true];
                    @endphp
                    @foreach ($filasMes as $id => $fila)
                        <tr wire:key="mes-{{ $id }}" @class(['font-semibold bg-gray-50 dark:bg-white/5' => $fila['strong'] ?? false])>
                            <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">{{ $fila['label'] }}</td>
                            @foreach ($meses as $numero => $mes)
                                @php
                                    $real = (float) ($fila['real'][$numero] ?? 0);
                                    $proy = (float) ($fila['plan'][$numero] ?? 0);
                                    $razon = $proy > 0 ? $real / $proy : null;
                                @endphp
                                <td class="{{ $celda }}">
                                    @if ($real > 0 || $proy > 0)
                                        <span @class(['block', $colorCumplimiento($razon) => $razon !== null])>{{ $real > 0 ? $tn($real, 0) : '0' }}</span>
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $proy > 0 ? 'de '.$tn($proy, 0) : 'sin plan' }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Facturación mensual: la hoja «Facturación Mensual». --}}
    <x-filament::section collapsible>
        <x-slot name="heading">Facturación mensual · aceite</x-slot>
        <x-slot name="description">Por la fecha de la factura. La última fila es a cuánto salió la tonelada: lo facturado del mes sobre las toneladas facturadas.</x-slot>
        @php
            $porMes = $cpo['invoiced_by_month'];
            $totalMes = array_fill(1, 12, ['amount' => 0.0, 'tons' => 0.0]);
            foreach ($porMes as $valores) {
                foreach ($valores as $m => $v) {
                    $totalMes[$m]['amount'] += $v['amount'];
                    $totalMes[$m]['tons'] += $v['tons'];
                }
            }
        @endphp
        <div class="overflow-x-auto -mx-2 px-2">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Cliente</th>
                        @foreach ($meses as $mes)
                            <th class="{{ $celdaTh }}">{{ $mes }}</th>
                        @endforeach
                        <th class="{{ $celdaTh }} border-l">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($porMes as $id => $valores)
                        <tr wire:key="fact-{{ $id }}">
                            <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">{{ $nombre($id) }}</td>
                            @foreach ($meses as $numero => $mes)
                                <td class="{{ $celda }}">{{ isset($valores[$numero]) ? $millones($valores[$numero]['amount']) : '' }}</td>
                            @endforeach
                            <td class="{{ $celda }} border-l font-semibold">{{ $millones(array_sum(array_column($valores, 'amount'))) }}</td>
                        </tr>
                    @endforeach
                    <tr class="font-semibold bg-gray-50 dark:bg-white/5">
                        <td class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 px-2 py-2">Total</td>
                        @foreach ($meses as $numero => $mes)
                            <td class="{{ $celda }}">{{ $totalMes[$numero]['amount'] > 0 ? $millones($totalMes[$numero]['amount']) : '' }}</td>
                        @endforeach
                        <td class="{{ $celda }} border-l">{{ $millones(array_sum(array_column($totalMes, 'amount'))) }}</td>
                    </tr>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 py-2">Precio por tonelada</td>
                        @foreach ($meses as $numero => $mes)
                            <td class="{{ $celda }}">{{ $totalMes[$numero]['tons'] > 0 ? $pesos($totalMes[$numero]['amount'] / $totalMes[$numero]['tons']) : '' }}</td>
                        @endforeach
                        @php $tTons = array_sum(array_column($totalMes, 'tons')); @endphp
                        <td class="{{ $celda }} border-l">{{ $tTons > 0 ? $pesos(array_sum(array_column($totalMes, 'amount')) / $tTons) : '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Fruta: proyectada contra recibida, por proveedor. --}}
    <x-filament::section collapsible>
        <x-slot name="heading">Fruta: recibida / proyectada por proveedor (tn)</x-slot>
        <x-slot name="description">La recibida se digita en «Fruta recibida»; la proyectada sale del plan.</x-slot>
        <div class="overflow-x-auto -mx-2 px-2">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Proveedor</th>
                        @foreach ($meses as $mes)
                            <th class="{{ $celdaTh }}">{{ $mes }}</th>
                        @endforeach
                        <th class="{{ $celdaTh }} border-l">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $filasFruta = collect($proveedores)->filter(fn ($n, $id) => isset($datos['fruit_plan'][$id]) || isset($datos['fruit_real'][$id]))
                            ->map(fn ($n, $id) => ['label' => $n, 'real' => $datos['fruit_real'][$id] ?? [], 'plan' => $datos['fruit_plan'][$id] ?? []])->all();
                        $filasFruta['total'] = ['label' => 'Total', 'real' => $datos['fruit_real_months'], 'plan' => $datos['fruit_plan_months'], 'strong' => true];
                    @endphp
                    @foreach ($filasFruta as $id => $fila)
                        @php $totalReal = array_sum($fila['real']); $totalPlan = array_sum($fila['plan']); @endphp
                        <tr wire:key="fruta-{{ $id }}" @class(['font-semibold bg-gray-50 dark:bg-white/5' => $fila['strong'] ?? false])>
                            <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">{{ $fila['label'] }}</td>
                            @foreach ($meses as $numero => $mes)
                                @php
                                    $real = (float) ($fila['real'][$numero] ?? 0);
                                    $proy = (float) ($fila['plan'][$numero] ?? 0);
                                @endphp
                                <td class="{{ $celda }}">
                                    @if ($real > 0 || $proy > 0)
                                        <span @class(['block', $colorCumplimiento($proy > 0 ? $real / $proy : null) => $proy > 0 && $real > 0])>{{ $real > 0 ? $tn($real, 0) : '—' }}</span>
                                        <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $proy > 0 ? 'de '.$tn($proy, 0) : 'sin plan' }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="{{ $celda }} border-l">
                                <span class="block font-semibold">{{ $tn($totalReal, 0) }}</span>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $totalPlan > 0 ? 'de '.$tn($totalPlan, 0).' · '.$pct($totalReal / $totalPlan) : '' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
