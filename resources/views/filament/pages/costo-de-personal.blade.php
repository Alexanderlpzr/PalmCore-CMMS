@php
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $horas = fn (float $valor): string => rtrim(rtrim(number_format($valor, 1, ',', '.'), '0'), ',');
    [$desde, $hasta, $finCorte] = $this->cutoffWindow;
    $dia = $this->dayReport;
    $corte = $this->cutoffReport;
    $informe = $this->report();
    $diasCorte = $desde->diffInDays($hasta) + 1;
    $esCorte = $vista === 'corte';
    $soloExtras = $this->onlyOvertime();
    $conceptos = $this->concepts();
    $fechaLarga = $this->day()->locale('es')->translatedFormat('l j \d\e F');
    $fechaTitulo = mb_strtoupper(mb_substr($fechaLarga, 0, 1)).mb_substr($fechaLarga, 1);
@endphp

<x-filament-panels::page>
    @php
        $enVivo = $this->isToday();
        $hayCosto = $informe['totals']['total'] > 0;
        $entraron = $dia['totals']['present'];
        $enNomina = max(1, $dia['totals']['people']);
        $avance = min(100, (int) round($entraron * 100 / $enNomina));
        $iniciales = fn (string $nombre): string => collect(preg_split('/\s+/', trim($nombre)))->filter()->take(2)->map(fn (string $p): string => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
    @endphp

    {{-- Hoy se refresca solo: cada carné que pasa por portería sube el costo sin recargar. --}}
    @if ($enVivo)
        <div wire:poll.30s.visible class="hidden" aria-hidden="true"></div>
    @endif

    {{-- El día que se mira y qué se reparte en las tortas. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::icon-button icon="heroicon-m-chevron-left" wire:click="cambiarDia(-1)" label="Día anterior" color="gray" />
            <x-filament::input.wrapper class="w-44">
                <x-filament::input type="date" wire:model.live="fecha" :max="$this->today()->toDateString()" aria-label="Día" />
            </x-filament::input.wrapper>
            <x-filament::icon-button icon="heroicon-m-chevron-right" wire:click="cambiarDia(1)" label="Día siguiente" color="gray" :disabled="$enVivo" />
            @unless ($enVivo)
                <x-filament::button size="sm" color="gray" wire:click="irAHoy">Hoy</x-filament::button>
            @endunless
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $fechaTitulo }}</span>
            @if ($enVivo)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-0.5 text-xs font-medium text-success-700 ring-1 ring-success-600/20 dark:bg-success-500/10 dark:text-success-400 dark:ring-success-500/30">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
                    </span>
                    En vivo
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-filament::tabs>
                <x-filament::tabs.item :active="! $soloExtras" wire:click="$set('contar', 'todo')" icon="heroicon-m-banknotes">
                    Costo completo
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$soloExtras" wire:click="$set('contar', 'extras')" icon="heroicon-m-clock">
                    Solo extras y recargos
                </x-filament::tabs.item>
            </x-filament::tabs>

            <x-filament::tabs>
                <x-filament::tabs.item :active="! $esCorte" wire:click="$set('vista', 'dia')" icon="heroicon-m-calendar">
                    Día
                </x-filament::tabs.item>
                <x-filament::tabs.item :active="$esCorte" wire:click="$set('vista', 'corte')" icon="heroicon-m-calendar-days">
                    Acumulado del corte
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- La cifra del día, con quiénes van entrando contra los que están en nómina. --}}
        <x-fronda.hero
            class="lg:col-span-2"
            :label="$soloExtras ? ($enVivo ? 'Extras y recargos de hoy' : 'Extras y recargos del '.$fechaLarga) : ($enVivo ? 'Lo que va costando hoy' : 'Costo del '.$fechaLarga)"
            :value="round($dia['totals']['total'])"
            prefix="$ "
            :badge="$dia['estimated'] ? 'Estimado' : null"
            icon="heroicon-o-banknotes"
        >
            <x-fronda.progress
                light
                :percent="$avance"
                :label="$entraron.' de '.$dia['totals']['people'].' '.($entraron === 1 ? 'persona entró' : 'personas entraron').($dia['totals']['inside'] > 0 ? ' · '.$dia['totals']['inside'].' '.($dia['totals']['inside'] === 1 ? 'sigue' : 'siguen').' adentro' : '')"
            />

            <x-slot name="stats">
                <x-fronda.hero-stat
                    label="Acumulado del corte"
                    :value="$pesos($corte['totals']['total'])"
                    :detail="'Del '.$desde->format('d/m').' al '.$hasta->format('d/m').' · día '.$diasCorte.' de '.($desde->diffInDays($finCorte) + 1)"
                />
                <x-fronda.hero-stat
                    label="Promedio por día"
                    :value="$pesos($corte['totals']['total'] / max(1, $diasCorte))"
                    :detail="'Corte al '.$finCorte->format('d/m').': '.$pesos($corte['totals']['total'] / max(1, $diasCorte) * ($desde->diffInDays($finCorte) + 1))"
                />
            </x-slot>
        </x-fronda.hero>

        {{-- Quién va entrando: cada fila es un jornal que se encendió. --}}
        {{-- Todas las entradas: se ven unas seis y el resto con scroll, con buscador. --}}
        <div x-data="{ q: '', norm(t) { return (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim() }, hay(el) { return ! this.q || el.dataset.buscar.includes(this.norm(this.q)) } }" class="flex flex-col self-start rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/5">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $enVivo ? 'Van entrando' : 'Entradas del día' }}</p>
                <span class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    @if (count($dia['entries']) > 0)
                        {{ count($dia['entries']) }} {{ count($dia['entries']) === 1 ? 'entrada' : 'entradas' }}
                    @endif
                    <x-filament::icon icon="heroicon-o-arrow-right-end-on-rectangle" class="h-5 w-5 text-gray-400" />
                </span>
            </div>

            @if (count($dia['entries']) > 0)
                <div class="border-b border-gray-100 px-3 py-2 dark:border-white/5">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input type="search" x-model="q" placeholder="Buscar persona o área" aria-label="Buscar en las entradas" />
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div x-ref="lista" class="max-h-[26.5rem] overflow-y-auto">
            @forelse ($dia['entries'] as $entrada)
                <div
                    wire:key="entrada-{{ $entrada['employee_id'] }}-{{ $entrada['at']->timestamp }}"
                    data-buscar="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($entrada['name'].' '.$entrada['area_label'])) }}"
                    x-show="hay($el)"
                    class="flex items-center gap-3 border-b border-gray-50 px-4 py-2.5 last:border-b-0 dark:border-white/5"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white" style="background-color: {{ \App\Domain\HumanResources\Services\LaborCostReport::AREA_COLORS[$entrada['area']] }}">
                        {{ $iniciales($entrada['name']) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $entrada['name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $entrada['area_label'] }} · {{ $entrada['at']->format('g:i a') }}</p>
                    </div>
                    <div class="text-right">
                        @unless ($soloExtras)
                            <p class="whitespace-nowrap text-sm font-semibold tabular-nums text-success-600 dark:text-success-400">+ {{ $pesos($entrada['jornal']) }}</p>
                        @endunless
                        @if ($entrada['inside'])
                            <p class="text-xs text-gray-500 dark:text-gray-400">adentro</p>
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex flex-1 flex-col items-center justify-center gap-2 px-6 py-10 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5">
                        <x-filament::icon icon="heroicon-o-qr-code" class="h-6 w-6 text-gray-400" />
                    </span>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $enVivo ? 'Todavía no ha entrado nadie' : 'Nadie entró este día' }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Cada carné que se escanea en portería aparece aquí y enciende su jornal.</p>
                </div>
            @endforelse
            </div>

            <p x-cloak x-show="q && ! [...$refs.lista.querySelectorAll('[data-buscar]')].some((el) => hay(el))" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Nadie con «<span x-text="q"></span>» en las entradas.
            </p>
        </div>
    </div>

    @if ($informe['totals']['without_salary'] > 0)
        <div class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-2 text-sm text-danger-700 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-300">
            {{ $informe['totals']['without_salary'] }} {{ $informe['totals']['without_salary'] === 1 ? 'persona que entró no tiene' : 'personas que entraron no tienen' }} salario: su jornal y sus extras cuentan en cero. Cárguelo en su ficha en «Personal».
        </div>
    @endif

    {{-- Los conceptos de la vista elegida, con la parte del total que se lleva cada uno. --}}
    <div @class(['grid grid-cols-2 gap-3', 'lg:grid-cols-4' => ! $soloExtras])>
        @foreach (array_filter([
            ['key' => 'jornal', 'detail' => $informe['totals']['present'].' '.($informe['totals']['present'] === 1 ? 'persona' : 'personas').' · salario ÷ 30 por día que entró'],
            ['key' => 'surcharges', 'detail' => $horas($informe['totals']['surcharge_hours']).' h nocturnas y dominicales'],
            ['key' => 'overtime', 'detail' => $horas($informe['totals']['overtime_hours']).' h extras'],
            ['key' => 'meals', 'detail' => $informe['totals']['meal_count'].' '.($informe['totals']['meal_count'] === 1 ? 'comida' : 'comidas')],
        ], fn (array $concepto): bool => in_array($concepto['key'], $conceptos, true)) as $concepto)
            <x-fronda.kpi
                :label="\App\Domain\HumanResources\Services\LaborCostReport::CONCEPTS[$concepto['key']]"
                :value="$pesos($informe['totals'][$concepto['key']])"
                :color="\App\Domain\HumanResources\Services\LaborCostReport::CONCEPT_COLORS[$concepto['key']]"
                :percent="$hayCosto ? $informe['totals'][$concepto['key']] * 100 / $informe['totals']['total'] : 0"
                :detail="$concepto['detail']"
            />
        @endforeach
    </div>

    @if (! $hayCosto)
        {{-- Sin nadie adentro no hay tortas que dibujar: se dice qué va a pasar. --}}
        <x-fronda.empty-state
            icon="heroicon-o-chart-pie"
            :heading="$soloExtras
                ? ($esCorte ? 'El corte todavía no tiene extras ni recargos' : ($enVivo ? 'Todavía no hay extras ni recargos hoy' : 'Este día no tuvo extras ni recargos'))
                : ($esCorte ? 'El corte todavía no tiene costo' : ($enVivo ? 'El día arranca en $ 0' : 'Este día no tuvo costo'))"
        >
            @if ($soloExtras)
                Aparecen cuando alguien pasa de su jornada o trabaja de noche o en domingo: al marcar la salida, o en vivo para quien sigue adentro{{ $enVivo ? '. Esta pantalla se actualiza sola' : '' }}.
            @else
                El costo crece con cada carné que se escanea en portería: el jornal de quien entra, sus recargos y horas extras, y sus comidas. Las tortas por área aparecen con la primera entrada{{ $enVivo ? ', y esta pantalla se actualiza sola' : '' }}.
            @endif
        </x-fronda.empty-state>
    @else
    {{-- Las tortas. --}}
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($this->charts() as $clave => $grafico)
            @livewire(\App\Filament\Widgets\HumanResources\LaborCostPieChart::class, [
                'chartHeading' => $grafico['heading'],
                'chartDescription' => $grafico['description'],
                'slices' => $grafico['slices'],
            ], key('torta-'.$contar.'-'.$clave))
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
                        @foreach ($conceptos as $clave)
                            <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">{{ \App\Domain\HumanResources\Services\LaborCostReport::CONCEPTS[$clave] }}</th>
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
                            @foreach ($conceptos as $clave)
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
                            <td class="px-3 py-2 text-right tabular-nums">{{ count($informe['rows']) }}</td>
                            @foreach ($conceptos as $clave)
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
        <div x-data="{ q: '', norm(t) { return (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim() }, hay(el) { return ! this.q || el.dataset.buscar.includes(this.norm(this.q)) } }">
        <div class="mb-3 max-w-sm">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input type="search" x-model="q" placeholder="Buscar trabajador, cargo o área" aria-label="Buscar en la tabla por trabajador" />
            </x-filament::input.wrapper>
        </div>
        <div x-ref="lista" class="-mx-2 overflow-x-auto px-2">
            <table class="w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr class="text-gray-600 dark:text-gray-300">
                        <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Trabajador</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-left font-semibold dark:border-white/10">Área</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">{{ $esCorte ? 'Días entró' : 'Entró' }}</th>
                        @unless ($soloExtras)
                            <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Jornal</th>
                        @endunless
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Recargos</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Horas extras</th>
                        @unless ($soloExtras)
                            <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Alimentación</th>
                        @endunless
                        <th class="border-b border-gray-200 px-3 py-2 text-right font-semibold dark:border-white/10">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($informe['rows'] as $fila)
                        <tr
                            wire:key="fila-{{ $fila['employee_id'] }}"
                            data-buscar="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii(collect([$fila['name'], $fila['code'], $fila['position'], $fila['area_label']])->filter()->join(' '))) }}"
                            x-show="hay($el)"
                            class="even:bg-gray-50 dark:even:bg-white/5"
                        >
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
                            @unless ($soloExtras)
                                <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $pesos($fila['jornal']) }}</td>
                            @endunless
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
                            @unless ($soloExtras)
                                <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">
                                    {{ $pesos($fila['meals']) }}
                                    @if ($fila['meal_count'] > 0)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['meal_count'] }} {{ $fila['meal_count'] === 1 ? 'comida' : 'comidas' }}</div>
                                    @endif
                                </td>
                            @endunless
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
            <p x-cloak x-show="q && ! [...$refs.lista.querySelectorAll('[data-buscar]')].some((el) => hay(el))" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                Nadie con «<span x-text="q"></span>» en esta tabla.
            </p>
        </div>
    </x-filament::section>

    @endif

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Costo directo: no incluye prestaciones sociales, aportes patronales ni parafiscales. El jornal cuenta desde que la persona entra a la extractora (salario ÷ 30 por cada día que entró); los recargos y las extras se valoran como en la nómina (salario ÷ divisor × factor) e incluyen horas sin confirmar.
    </p>
</x-filament-panels::page>
