@php
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $horas = fn (float $valor): string => rtrim(rtrim(number_format($valor, 1, ',', '.'), '0'), ',');
    [$desde, $hasta, $finCorte] = $this->cutoffWindow;
    $dia = $this->dayReport;
    $corte = $this->cutoffReport;
    $informe = $this->report();
    $diasCorte = $desde->diffInDays($hasta) + 1;
    $esCorte = $vista === 'corte';
    $fechaLarga = $this->day()->locale('es')->translatedFormat('l j \d\e F');
    $fechaTitulo = mb_strtoupper(mb_substr($fechaLarga, 0, 1)).mb_substr($fechaLarga, 1);
@endphp

<x-filament-panels::page>
    {{-- El día que se mira y qué se reparte en las tortas. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="cambiarDia(-1)" label="Día anterior" color="gray" />
            <x-filament::input.wrapper class="w-44">
                <x-filament::input type="date" wire:model.live="fecha" :max="$this->today()->toDateString()" aria-label="Día" />
            </x-filament::input.wrapper>
            <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="cambiarDia(1)" label="Día siguiente" color="gray" :disabled="$this->isToday()" />
            @unless ($this->isToday())
                <x-filament::button size="sm" color="gray" wire:click="irAHoy">Hoy</x-filament::button>
            @endunless
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $fechaTitulo }}</span>
        </div>

        <x-filament::tabs>
            <x-filament::tabs.item :active="! $esCorte" wire:click="$set('vista', 'dia')" icon="heroicon-m-calendar">
                Día
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$esCorte" wire:click="$set('vista', 'corte')" icon="heroicon-m-calendar-days">
                Acumulado del corte
            </x-filament::tabs.item>
        </x-filament::tabs>
    </div>

    {{-- Las tres cifras grandes: el día, lo que va del corte y el promedio. --}}
    <div class="grid gap-3 md:grid-cols-3">
        <div class="flex flex-col gap-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Costo del día</p>
                @if ($dia['estimated'])
                    <x-filament::badge color="warning" size="sm">Estimado</x-filament::badge>
                @endif
            </div>
            <p class="whitespace-nowrap text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $pesos($dia['totals']['total']) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $dia['totals']['people'] }} {{ $dia['totals']['people'] === 1 ? 'persona' : 'personas' }} en nómina ·
                {{ $dia['totals']['present'] }} {{ $dia['totals']['present'] === 1 ? 'entró' : 'entraron' }}
                @if ($dia['totals']['inside'] > 0)
                    · {{ $dia['totals']['inside'] }} {{ $dia['totals']['inside'] === 1 ? 'sigue' : 'siguen' }} adentro
                @endif
            </p>
        </div>

        <div class="flex flex-col gap-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Acumulado del corte</p>
                @if ($corte['estimated'])
                    <x-filament::badge color="warning" size="sm">Estimado</x-filament::badge>
                @endif
            </div>
            <p class="whitespace-nowrap text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $pesos($corte['totals']['total']) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Del {{ $desde->format('d/m') }} al {{ $hasta->format('d/m') }} · día {{ $diasCorte }} de {{ $desde->diffInDays($finCorte) + 1 }} (corte al {{ $finCorte->format('d/m') }})
            </p>
        </div>

        <div class="flex flex-col gap-1 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Promedio por día del corte</p>
            <p class="whitespace-nowrap text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $pesos($corte['totals']['total'] / max(1, $diasCorte)) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Si sigue así, el corte cerraría en {{ $pesos($corte['totals']['total'] / max(1, $diasCorte) * ($desde->diffInDays($finCorte) + 1)) }}
            </p>
        </div>
    </div>

    @if ($informe['totals']['without_salary'] > 0)
        <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-2 text-sm text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
            {{ $informe['totals']['without_salary'] }} {{ $informe['totals']['without_salary'] === 1 ? 'trabajador activo no tiene' : 'trabajadores activos no tienen' }} salario: su jornal y sus extras cuentan en cero. Cárguelo en su ficha en «Personal».
        </div>
    @endif

    {{-- Los conceptos de la vista elegida. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['key' => 'jornal', 'detail' => $esCorte ? 'Salario ÷ 30 por cada día del corte' : 'Salario ÷ 30 de cada persona en nómina'],
            ['key' => 'surcharges', 'detail' => $horas($informe['totals']['surcharge_hours']).' h nocturnas y dominicales'],
            ['key' => 'overtime', 'detail' => $horas($informe['totals']['overtime_hours']).' h extras'],
            ['key' => 'meals', 'detail' => $informe['totals']['meal_count'].' '.($informe['totals']['meal_count'] === 1 ? 'comida' : 'comidas')],
        ] as $concepto)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ \App\Domain\HumanResources\Services\LaborCostReport::CONCEPT_COLORS[$concepto['key']] }}"></span>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ \App\Domain\HumanResources\Services\LaborCostReport::CONCEPTS[$concepto['key']] }}</p>
                </div>
                <p class="mt-1 whitespace-nowrap text-lg font-bold tabular-nums text-gray-950 sm:text-2xl dark:text-white">{{ $pesos($informe['totals'][$concepto['key']]) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $concepto['detail'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Las tortas. --}}
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->charts() as $clave => $grafico)
            @livewire(\App\Filament\Widgets\HumanResources\LaborCostPieChart::class, [
                'chartHeading' => $grafico['heading'],
                'chartDescription' => $grafico['description'],
                'slices' => $grafico['slices'],
            ], key('torta-'.$clave))
        @endforeach
    </div>

    {{-- Resumen por área. --}}
    <x-filament::section
        heading="Por área"
        :description="$esCorte ? 'Del '.$desde->format('d/m').' al '.$hasta->format('d/m/Y').'.' : 'El '.$fechaLarga.'.'"
    >
        <div class="-mx-2 overflow-x-auto px-2">
            <table class="w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Área</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Personas</th>
                        @foreach (\App\Domain\HumanResources\Services\LaborCostReport::CONCEPTS as $etiqueta)
                            <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">{{ $etiqueta }}</th>
                        @endforeach
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Total</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">% del total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($informe['areas'] as $area)
                        <tr class="even:bg-gray-50 dark:even:bg-white/5">
                            <td class="whitespace-nowrap border-b border-gray-100 px-3 py-2 dark:border-white/5">
                                <span class="mr-1.5 inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $area['color'] }}"></span>{{ $area['label'] }}
                            </td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $area['people'] }}</td>
                            @foreach (array_keys(\App\Domain\HumanResources\Services\LaborCostReport::CONCEPTS) as $clave)
                                <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $pesos($area[$clave]) }}</td>
                            @endforeach
                            <td class="border-b border-gray-100 px-3 py-2 text-right font-semibold tabular-nums dark:border-white/5">{{ $pesos($area['total']) }}</td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums text-gray-500 dark:border-white/5 dark:text-gray-400">
                                {{ $informe['totals']['total'] > 0 ? number_format($area['total'] * 100 / $informe['totals']['total'], 1, ',', '.') : '0' }} %
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No hay trabajadores activos con costo en este periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($informe['areas'])
                    <tfoot>
                        <tr class="bg-gray-50 font-semibold dark:bg-white/5">
                            <td class="px-3 py-2">Total</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $informe['totals']['people'] }}</td>
                            @foreach (array_keys(\App\Domain\HumanResources\Services\LaborCostReport::CONCEPTS) as $clave)
                                <td class="px-3 py-2 text-right tabular-nums">{{ $pesos($informe['totals'][$clave]) }}</td>
                            @endforeach
                            <td class="px-3 py-2 text-right tabular-nums">{{ $pesos($informe['totals']['total']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">100 %</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>

    {{-- El detalle por trabajador: cuánto cuesta el día (o el corte) de cada uno. --}}
    <x-filament::section
        heading="Por trabajador"
        :description="($esCorte ? 'Lo que va del corte' : 'El día').' de cada persona, de mayor a menor costo. «Estimado» quiere decir que tiene horas sin confirmar o que sigue adentro.'"
        collapsible
    >
        <div class="-mx-2 overflow-x-auto px-2">
            <table class="w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Trabajador</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Área</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">{{ $esCorte ? 'Días entró' : 'Entró' }}</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Jornal</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Recargos</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Horas extras</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Alimentación</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($informe['rows'] as $fila)
                        <tr wire:key="fila-{{ $fila['employee_id'] }}" class="even:bg-gray-50 dark:even:bg-white/5">
                            <td class="border-b border-gray-100 px-3 py-2 dark:border-white/5">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $fila['name'] }}</span>
                                    @if ($fila['inside'])
                                        <x-filament::badge color="success" size="sm">Adentro</x-filament::badge>
                                    @elseif ($fila['estimated'])
                                        <x-filament::badge color="warning" size="sm">Estimado</x-filament::badge>
                                    @endif
                                    @if ($fila['without_salary'])
                                        <x-filament::badge color="danger" size="sm">Sin salario</x-filament::badge>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ collect([$fila['code'], $fila['position']])->filter()->join(' · ') }}</div>
                            </td>
                            <td class="whitespace-nowrap border-b border-gray-100 px-3 py-2 dark:border-white/5">
                                <span class="mr-1.5 inline-block h-2 w-2 rounded-full" style="background-color: {{ \App\Domain\HumanResources\Services\LaborCostReport::AREA_COLORS[$fila['area']] }}"></span>{{ $fila['area_label'] }}
                            </td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">
                                @if ($esCorte)
                                    {{ $fila['present'] }} de {{ $fila['days'] }}
                                @else
                                    {{ $fila['present'] > 0 ? 'Sí' : 'No' }}
                                @endif
                            </td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $pesos($fila['jornal']) }}</td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">
                                {{ $pesos($fila['surcharges']) }}
                                @if ($fila['surcharge_hours'] > 0)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $horas($fila['surcharge_hours']) }} h</div>
                                @endif
                            </td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">
                                {{ $pesos($fila['overtime']) }}
                                @if ($fila['overtime_hours'] > 0)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $horas($fila['overtime_hours']) }} h</div>
                                @endif
                            </td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">
                                {{ $pesos($fila['meals']) }}
                                @if ($fila['meal_count'] > 0)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['meal_count'] }} {{ $fila['meal_count'] === 1 ? 'comida' : 'comidas' }}</div>
                                @endif
                            </td>
                            <td class="border-b border-gray-100 px-3 py-2 text-right font-semibold tabular-nums dark:border-white/5">{{ $pesos($fila['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No hay trabajadores activos con costo en este periodo.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Costo directo: no incluye prestaciones sociales, aportes patronales ni parafiscales. El jornal corre para todo trabajador activo, marque o no en portería; los recargos y las extras se valoran como en la nómina (salario ÷ divisor × factor) e incluyen horas sin confirmar.
    </p>
</x-filament-panels::page>
