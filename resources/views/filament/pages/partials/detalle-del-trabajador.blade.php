{{--
    Todo lo de una persona en el corte (EmployeeDetailAction). De arriba abajo: la cifra
    principal, los indicadores, en qué se le fueron las horas y lo que costó, el calendario
    del corte, sus días uno por uno y sus novedades.
--}}
@use('App\Domain\HumanResources\Enums\AttendanceDayStatus')
@use('App\Domain\HumanResources\Services\LaborCostReport')
@use('App\Support\FrondaPalette')
@use('App\Support\Hours')

@php
    $t = $resumen['totals'];
    $previo = $resumen['previous'];
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $horas = fn (float $h): string => Hours::format($h) ?: '0 h';
    $pagadas = $t['overtime'] + $t['surcharges'];
    $diasCorte = $resumen['from']->diffInDays($resumen['until']) + 1;
    $variacion = $previo && ($previo['overtime'] + $previo['surcharges']) > 0
        ? ($pagadas - $previo['overtime'] - $previo['surcharges']) * 100 / ($previo['overtime'] + $previo['surcharges'])
        : null;
    $composicion = array_filter([
        ['Ordinarias', $t['ordinary'], FrondaPalette::Petrol[600]],
        ['Recargos', $t['surcharges'], FrondaPalette::Petrol[300]],
        ['Extras', $t['overtime'], FrondaPalette::Brand[600]],
    ], fn (array $parte): bool => $parte[1] > 0);
    $sumaComposicion = max(0.01, array_sum(array_column($composicion, 1)));
    $diasConAlgo = array_values(array_filter($resumen['days'], fn (array $d): bool => $d['worked'] > 0 || $d['status'] || $d['novelty'] || $d['entry']));
    $primerDia = $resumen['from']->dayOfWeekIso - 1;
@endphp

<div class="space-y-5">
    {{-- La cifra principal: las horas que se pagan por encima del jornal. --}}
    <x-fronda.hero
        label="Horas extras y recargos del corte"
        :value="$pagadas"
        :decimals="1"
        suffix=" h"
        :badge="$t['proposed'] > 0 ? $t['proposed'].' '.($t['proposed'] === 1 ? 'día sin confirmar' : 'días sin confirmar') : null"
        icon="heroicon-o-clock"
    >
        <x-fronda.progress
            light
            :percent="($t['confirmed'] + $t['proposed']) > 0 ? $t['confirmed'] * 100 / ($t['confirmed'] + $t['proposed']) : 100"
            :label="'Días confirmados: '.$t['confirmed'].' de '.($t['confirmed'] + $t['proposed'])"
        />

        <x-slot name="stats">
            <x-fronda.hero-stat
                label="Horas trabajadas"
                :value="$horas($t['worked'])"
                :detail="$t['worked_days'].' '.($t['worked_days'] === 1 ? 'día' : 'días').' de '.$diasCorte"
            />
            <x-fronda.hero-stat
                label="Contra su corte anterior"
                :value="$variacion === null ? '—' : ($variacion >= 0 ? '+' : '').number_format($variacion, 0, ',', '.').' %'"
                :detail="$previo ? $horas($previo['overtime'] + $previo['surcharges']).' de extras y recargos a esta altura' : 'Sin días en el corte anterior'"
            />
        </x-slot>
    </x-fronda.hero>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-fronda.kpi label="Área" :value="$resumen['area']" icon="heroicon-o-building-office" :detail="$resumen['employee']->position ?: 'Sin cargo en la ficha'" />
        <x-fronda.kpi
            label="Horas extras"
            :value="$horas($t['overtime'])"
            :color="FrondaPalette::Brand[600]"
            :detail="$previo ? 'Antes: '.$horas($previo['overtime']) : 'Diurnas, nocturnas y dominicales'"
        />
        <x-fronda.kpi
            label="Recargos"
            :value="$horas($t['surcharges'])"
            :color="FrondaPalette::Petrol[300]"
            :detail="$previo ? 'Antes: '.$horas($previo['surcharges']) : 'Nocturnos y dominicales'"
        />
        <x-fronda.kpi
            label="Alimentación"
            :value="$t['meal_count'].' '.($t['meal_count'] === 1 ? 'comida' : 'comidas')"
            color="#d97706"
            :detail="$pesos($t['meal_amount']).' · días confirmados'"
        />
    </div>

    <div @class(['grid gap-3', 'md:grid-cols-2' => $conPesos])>
        {{-- En qué se le fueron las horas. --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">En qué se le fueron las horas</p>
            <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                @foreach ($composicion as [$nombre, $valor, $color])
                    <div style="width: {{ $valor * 100 / $sumaComposicion }}%; background-color: {{ $color }}" title="{{ $nombre }}"></div>
                @endforeach
            </div>
            <div class="mt-3 space-y-1.5 text-sm">
                @forelse ($composicion as [$nombre, $valor, $color])
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2 text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $color }}"></span>{{ $nombre }}</span>
                        <span class="tabular-nums text-gray-950 dark:text-white">{{ $horas($valor) }} <span class="text-xs text-gray-400">{{ number_format($valor * 100 / $sumaComposicion, 0) }} %</span></span>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400">Sin horas en el corte.</p>
                @endforelse
            </div>
        </div>

        {{-- Lo que costó, como en «Costo de personal». --}}
        @if ($conPesos)
            @php
                $costo = $resumen['cost'];
                $conceptos = $costo ? array_filter(LaborCostReport::CONCEPTS, fn (string $etiqueta, string $clave): bool => $costo[$clave] > 0, ARRAY_FILTER_USE_BOTH) : [];
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Lo que costó</p>
                    <p class="text-lg font-bold tabular-nums text-gray-950 dark:text-white">{{ $pesos($costo['total'] ?? 0) }}</p>
                </div>
                @if ($costo && $costo['total'] > 0)
                    <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        @foreach ($conceptos as $clave => $etiqueta)
                            <div style="width: {{ $costo[$clave] * 100 / $costo['total'] }}%; background-color: {{ LaborCostReport::CONCEPT_COLORS[$clave] }}" title="{{ $etiqueta }}"></div>
                        @endforeach
                    </div>
                    <div class="mt-3 space-y-1.5 text-sm">
                        @foreach ($conceptos as $clave => $etiqueta)
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ LaborCostReport::CONCEPT_COLORS[$clave] }}"></span>{{ $etiqueta }}</span>
                                <span class="tabular-nums text-gray-950 dark:text-white">{{ $pesos($costo[$clave]) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Jornal = salario ÷ 30 por día que entró.{{ $costo['estimated'] ? ' Incluye horas sin confirmar.' : '' }}</p>
                @else
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ (float) $resumen['employee']->base_salary > 0 ? 'Sin costo en el corte: no ha entrado.' : 'No tiene salario en su ficha: su costo cuenta en cero.' }}</p>
                @endif
            </div>
        @endif
    </div>

    {{-- El calendario del corte: un vistazo a qué días vino y cómo están. --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Calendario del corte</p>
            <div class="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-success-100 ring-1 ring-success-300 dark:bg-success-500/20"></span>Confirmado</span>
                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-warning-100 ring-1 ring-warning-300 dark:bg-warning-500/20"></span>Por confirmar</span>
                <span class="flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-info-100 ring-1 ring-info-300 dark:bg-info-500/20"></span>Novedad</span>
                <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>Con extras</span>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-7 gap-1.5 text-center text-xs">
            @foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $diaSemana)
                <div class="font-medium text-gray-400">{{ $diaSemana }}</div>
            @endforeach
            @for ($i = 0; $i < $primerDia; $i++)
                <div></div>
            @endfor
            @foreach ($resumen['days'] as $dia)
                <div
                    title="{{ $dia['date']->format('d/m') }}{{ $dia['worked'] > 0 ? ' · '.$horas($dia['worked']) : '' }}{{ $dia['novelty'] ? ' · '.$dia['novelty'] : '' }}"
                    @class([
                        'relative flex min-h-12 flex-col items-center justify-center rounded-lg px-1 py-1.5 ring-1',
                        'bg-success-50 ring-success-200 dark:bg-success-500/10 dark:ring-success-500/30' => $dia['status'] === AttendanceDayStatus::Confirmada,
                        'bg-warning-50 ring-warning-200 dark:bg-warning-500/10 dark:ring-warning-500/30' => $dia['status'] === AttendanceDayStatus::Propuesta,
                        'bg-info-50 ring-info-200 dark:bg-info-500/10 dark:ring-info-500/30' => ! $dia['status'] && $dia['novelty'],
                        'bg-gray-50 ring-gray-100 dark:bg-white/5 dark:ring-white/5' => ! $dia['status'] && ! $dia['novelty'],
                    ])
                >
                    <span @class(['font-semibold', 'text-gray-950 dark:text-white' => $dia['status'], 'text-gray-400' => ! $dia['status']])>{{ $dia['date']->day }}</span>
                    <span class="tabular-nums text-[11px] text-gray-500 dark:text-gray-400">{{ $dia['worked'] > 0 ? rtrim(rtrim(number_format($dia['worked'], 1, ',', ''), '0'), ',').' h' : ($dia['novelty'] ? mb_substr($dia['novelty'], 0, 6) : '') }}</span>
                    @if ($dia['overtime'] > 0)
                        <span class="absolute right-1 top-1 h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Sus días, uno por uno. --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <p class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-950 dark:border-white/5 dark:text-white">Día a día</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2 text-left font-medium">Fecha</th>
                        <th class="px-3 py-2 text-left font-medium">Entrada</th>
                        <th class="px-3 py-2 text-left font-medium">Salida</th>
                        <th class="px-3 py-2 text-right font-medium">Trabajadas</th>
                        <th class="px-3 py-2 text-right font-medium">Recargos</th>
                        <th class="px-3 py-2 text-right font-medium">Extras</th>
                        <th class="px-3 py-2 text-left font-medium">Comidas</th>
                        <th class="px-3 py-2 text-left font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($diasConAlgo as $dia)
                        <tr class="border-t border-gray-100 even:bg-gray-50 dark:border-white/5 dark:even:bg-white/5">
                            <td class="whitespace-nowrap px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $dia['date']->locale('es')->translatedFormat('D d/m') }}</td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ $dia['entry']?->format('g:i a') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 tabular-nums">{{ $dia['exit']?->format('g:i a') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ Hours::format($dia['worked']) ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">{{ Hours::format($dia['surcharges']) ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-semibold tabular-nums text-primary-700 dark:text-primary-400">{{ Hours::format($dia['overtime']) ?: '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-300">{{ $dia['meals'] ? implode(', ', $dia['meals']) : '—' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap items-center gap-1">
                                    @if ($dia['status'] === AttendanceDayStatus::Confirmada)
                                        <x-filament::badge color="success" size="sm">Confirmado</x-filament::badge>
                                    @elseif ($dia['status'] === AttendanceDayStatus::Propuesta)
                                        <x-filament::badge color="warning" size="sm">Por confirmar</x-filament::badge>
                                    @elseif ($dia['novelty'])
                                        <x-filament::badge color="info" size="sm">{{ $dia['novelty'] }}</x-filament::badge>
                                    @endif
                                    @if ($dia['adjusted'])
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Ajustado a mano</span>
                                    @endif
                                </div>
                                @if ($dia['anomalies'])
                                    <p class="mt-0.5 text-xs text-danger-600 dark:text-danger-400">{{ implode(' · ', $dia['anomalies']) }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">No marcó ni tiene novedades en este corte.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($diasConAlgo)
                    <tfoot>
                        <tr class="border-t border-gray-200 bg-gray-50 font-semibold dark:border-white/10 dark:bg-white/5">
                            <td class="px-3 py-2" colspan="3">Total</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $horas($t['worked']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $horas($t['surcharges']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $horas($t['overtime']) }}</td>
                            <td class="px-3 py-2" colspan="2">{{ $t['meal_count'] }} {{ $t['meal_count'] === 1 ? 'comida' : 'comidas' }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Novedades del corte. --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">Novedades</p>
        @forelse ($resumen['novelties'] as $novedad)
            <div class="mt-2 flex items-center justify-between gap-2 text-sm">
                <span class="flex items-center gap-2">
                    <x-filament::badge color="info" size="sm">{{ $novedad['type'] }}</x-filament::badge>
                    <span class="text-gray-600 dark:text-gray-300">Del {{ $novedad['from']->format('d/m') }} al {{ $novedad['to']->format('d/m') }}{{ $novedad['notes'] ? ' · '.$novedad['notes'] : '' }}</span>
                </span>
                <span class="whitespace-nowrap tabular-nums text-gray-950 dark:text-white">{{ $novedad['days'] }} {{ $novedad['days'] === 1 ? 'día' : 'días' }} en el corte</span>
            </div>
        @empty
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sin incapacidades, vacaciones ni permisos en este corte.</p>
        @endforelse
    </div>
</div>
