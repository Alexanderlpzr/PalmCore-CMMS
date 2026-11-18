@php
    $d = $this->data();
    $c = $d['current'];
    $y = $d['ytd'];
    $meses = \App\Domain\Commercial\Services\MonthlyManagementReport::MONTHS;
    $cortos = \App\Filament\Pages\PlanAnual::MONTHS;
    $mes = $meses[$this->month];
    $tn = fn (?float $v, int $dec = 0): string => $v === null ? '—' : number_format($v, $dec, ',', '.');
    $pesos = fn (?float $v): string => $v === null ? '—' : '$'.number_format($v, 0, ',', '.');
    $millones = fn (?float $v): string => $v === null ? '—' : '$'.number_format($v / 1_000_000, 0, ',', '.').' M';
    $pct = fn (?float $v): string => $v === null ? '—' : number_format($v, 1, ',', '.').' %';
    $celda = 'text-right tabular-nums px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap';
    $celdaTh = 'text-right font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap';
    $mt = $d['maintenance_month'];
    $presupuestoMes = $mt['budget'] ?? null;
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::input.wrapper class="w-28">
            <x-filament::input type="number" wire:model.live.debounce.500ms="year" min="2020" max="2100" aria-label="Año" />
        </x-filament::input.wrapper>
        <x-filament::input.wrapper class="w-44">
            <x-filament::input.select wire:model.live="month" aria-label="Mes">
                @foreach ($meses as $numero => $nombre)
                    <option value="{{ $numero }}">{{ $nombre }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $mes }} de {{ $this->year }} y lo que va del año hasta {{ mb_strtolower($mes) }}.</span>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-fronda.hero
            class="lg:col-span-2"
            :label="'Costo de transformación por tonelada · '.$mes"
            :value="$c['cost_per_ton']"
            :missing="$c['cost_per_ton'] === null"
            prefix="$"
            :badge="$y['cost_per_ton'] === null ? null : 'Año: $'.number_format($y['cost_per_ton'], 0, ',', '.').' por tonelada'"
            icon="heroicon-o-scale"
        >
            <p class="text-sm text-white/80">
                Personal más mantenimiento, por cada tonelada de fruta procesada. No es un margen: la compra de fruta no está en la plataforma.
            </p>
            <x-slot name="stats">
                <x-fronda.hero-stat label="Fruta procesada" :value="$tn($c['tons']).' tn'" :detail="'Año: '.$tn($y['tons']).' tn'" />
                <x-fronda.hero-stat label="Costo del mes" :value="$millones($c['cost'])" :detail="'Personal '.$millones($c['personnel']).' · mantenimiento '.$millones($c['maintenance'])" />
            </x-slot>
        </x-fronda.hero>

        <div class="grid gap-4">
            <x-fronda.kpi label="Facturado" :value="$millones($c['invoiced'])" icon="heroicon-o-banknotes" :detail="'Aceite despachado: '.$tn($c['oil'], 1).' tn · año '.$millones($y['invoiced'])" />
            <x-fronda.kpi label="Cartera" :value="$millones($d['receivable'])" icon="heroicon-o-clock"
                :tone="$d['overdue'] > 0 ? 'danger' : 'ok'"
                :detail="$d['overdue'] > 0 ? 'Vencido '.$millones($d['overdue']) : 'Nada vencido'" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-fronda.kpi label="Costo de personal" :value="$millones($c['personnel'])" icon="heroicon-o-user-group"
            :detail="'Año: '.$millones($y['personnel']).($d['personnel_estimated'] ? ' · estimado: hay horas sin confirmar' : '')" />
        <x-fronda.kpi label="Costo de mantenimiento" :value="$millones($c['maintenance'])" icon="heroicon-o-wrench-screwdriver"
            :tone="$presupuestoMes !== null && ($c['maintenance'] ?? 0) > $presupuestoMes ? 'danger' : 'neutral'"
            :percent="$presupuestoMes ? min(100, ($c['maintenance'] ?? 0) / $presupuestoMes * 100) : null" color="#00384C"
            :detail="$presupuestoMes !== null ? 'De '.$millones($presupuestoMes).' presupuestados · '.($mt['work_orders'] ?? 0).' OT' : ($mt['work_orders'] ?? 0).' OT cerradas · sin presupuesto'" />
        <x-fronda.kpi label="Energía por tonelada" :value="$c['kwh_per_ton'] === null ? '—' : $tn($c['kwh_per_ton'], 1).' kWh'" icon="heroicon-o-bolt"
            :detail="'Año: '.($y['kwh_per_ton'] === null ? '—' : $tn($y['kwh_per_ton'], 1).' kWh/tn')" />
        <x-fronda.kpi label="Disponibilidad de planta" :value="$pct($c['availability'])" icon="heroicon-o-cog-6-tooth"
            :detail="'Eficiencia '.$pct($c['efficiency'])" />
        <x-fronda.kpi label="Hallazgos SST abiertos" :value="(string) $d['sst_open']" icon="heroicon-o-shield-exclamation"
            :tone="$d['sst_overdue'] > 0 ? 'danger' : 'ok'"
            :detail="$d['sst_overdue'] > 0 ? $d['sst_overdue'].' vencidos' : 'Ninguno vencido'" />
        <x-fronda.kpi label="Aceite en tanques"
            :value="! $d['tanks'] ? 'Sin medir' : ($d['tanks']['stale'] ? 'Hay que medir' : $tn($d['tanks']['total'], 1).' tn')"
            icon="heroicon-o-beaker"
            :tone="$d['tanks'] && $d['tanks']['stale'] ? 'warning' : 'neutral'"
            :detail="$d['tanks'] ? 'Medición del '.$d['tanks']['date']->format('d/m/Y') : null" />
        <x-fronda.kpi label="Aceite despachado en el año" :value="$tn($y['oil'], 1).' tn'" icon="heroicon-o-truck"
            :detail="'Facturado en el año '.$millones($y['invoiced'])" />
        <x-fronda.kpi label="Costo en el año" :value="$millones($y['cost'])" icon="heroicon-o-calculator"
            :detail="'Mantenimiento '.$millones($y['maintenance']).($y['maintenance_budget'] > 0 ? ' de '.$millones($y['maintenance_budget']) : '')" />
    </div>

    <x-filament::section>
        <x-slot name="heading">Mes a mes</x-slot>
        <x-slot name="description">Cada fila es el total de un área; el mes elegido va resaltado. Los costos de los meses que no han llegado quedan en blanco.</x-slot>
        <div class="overflow-x-auto -mx-2 px-2">
            <table class="w-full text-sm border-separate border-spacing-0">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Indicador</th>
                        @foreach ($cortos as $n => $corto)
                            <th @class([$celdaTh, 'bg-primary-50 dark:bg-primary-500/10' => $n === (int) $this->month])>{{ $corto }}</th>
                        @endforeach
                        <th class="{{ $celdaTh }} border-l">Año</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([
                        ['Fruta procesada (tn)', 'tons', fn ($v) => $tn($v), $tn($y['tons'])],
                        ['Aceite despachado (tn)', 'oil', fn ($v) => $tn($v), $tn($y['oil'])],
                        ['Facturado', 'invoiced', fn ($v) => $millones($v), $millones($y['invoiced'])],
                        ['Costo de personal', 'personnel', fn ($v) => $millones($v), $millones($y['personnel'])],
                        ['Costo de mantenimiento', 'maintenance', fn ($v) => $millones($v), $millones($y['maintenance'])],
                        ['Costo por tonelada', 'cost_per_ton', fn ($v) => $pesos($v), $pesos($y['cost_per_ton'])],
                        ['kWh por tonelada', 'kwh_per_ton', fn ($v) => $tn($v, 1), $tn($y['kwh_per_ton'], 1)],
                        ['Disponibilidad', 'availability', fn ($v) => $pct($v), ''],
                    ] as [$nombre, $clave, $formato, $anual])
                        <tr wire:key="dir-{{ $clave }}" @class(['font-semibold' => $clave === 'cost_per_ton'])>
                            <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">{{ $nombre }}</td>
                            @foreach ($cortos as $n => $corto)
                                @php $valor = $d['months'][$n][$clave]; @endphp
                                <td @class([$celda, 'bg-primary-50 dark:bg-primary-500/10' => $n === (int) $this->month])>
                                    {{ $valor === null || (is_numeric($valor) && (float) $valor == 0.0 && $d['months'][$n]['future']) ? '' : $formato((float) $valor) }}
                                </td>
                            @endforeach
                            <td class="{{ $celda }} border-l font-semibold">{{ $anual }}</td>
                        </tr>
                    @endforeach
                    {{-- El semáforo de la Directora: lo nacional debe ser más de la mitad del aceite. --}}
                    @php
                        $semaforo = fn (?float $s): string => match (\App\Domain\Commercial\Enums\Market::nationalShareTone($s)) {
                            'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300',
                            'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300',
                            'success' => 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300',
                            default => '',
                        };
                    @endphp
                    <tr wire:key="dir-nacional" class="font-semibold">
                        <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">% aceite nacional</td>
                        @foreach ($cortos as $n => $corto)
                            @php $parte = $d['mix']['months'][$n]['real_share']; @endphp
                            <td @class([$celda, $semaforo($parte)])>{{ $parte === null ? '' : $pct($parte * 100) }}</td>
                        @endforeach
                        <td @class([$celda, 'border-l', $semaforo($d['mix']['year']['real_share'])])>{{ $d['mix']['year']['real_share'] === null ? '—' : $pct($d['mix']['year']['real_share'] * 100) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Personal: jornales, novedades pagadas, recargos, horas extras y alimentación, sin prestaciones ni aportes (el mismo cálculo de «Costo de personal»). Mantenimiento: el costo real de las OT cerradas en el mes. Fruta y energía: los indicadores mensuales de planta.
        </p>
    </x-filament::section>

    <div class="grid gap-4 lg:grid-cols-2">
        @livewire(\App\Filament\Widgets\HumanResources\LaborCostPieChart::class, [
            'chartHeading' => 'Personal por área · '.$mes,
            'chartDescription' => 'Lo que costó cada área, en pesos. Totales: ningún sueldo individual.',
            'slices' => collect($d['personnel_areas'])->map(fn ($a) => ['label' => $a['label'].' ('.$a['people'].')', 'value' => $a['total'], 'color' => $a['color']])->all(),
        ], key('dir-areas-'.$this->year.'-'.$this->month))

        <x-filament::section>
            <x-slot name="heading">De qué se compone · {{ $mes }}</x-slot>
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($d['personnel_concepts'] as $concepto)
                        <tr>
                            <td class="py-1.5"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $concepto['color'] }}"></span>{{ $concepto['label'] }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $pesos($concepto['total']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t border-gray-200 font-semibold dark:border-white/10">
                        <td class="py-1.5">Personal</td>
                        <td class="py-1.5 text-right tabular-nums">{{ $pesos($c['personnel']) }}</td>
                    </tr>
                    @if ($mt)
                        @foreach (['labor' => 'Mano de obra de las OT', 'parts' => 'Repuestos', 'consumables' => 'Insumos', 'external' => 'Servicios externos'] as $clave => $nombre)
                            <tr>
                                <td class="py-1.5 pl-4 text-gray-600 dark:text-gray-300">{{ $nombre }}</td>
                                <td class="py-1.5 text-right tabular-nums">{{ $pesos($mt[$clave]) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-gray-200 font-semibold dark:border-white/10">
                            <td class="py-1.5">Mantenimiento ({{ $mt['work_orders'] }} OT)</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $pesos($mt['total']) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
