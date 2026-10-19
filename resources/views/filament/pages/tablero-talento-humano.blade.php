@php
    $horas = fn (float $h): string => rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',');
    $cambio = fn (float $ahora, float $antes): ?string => $antes > 0 ? (($ahora >= $antes ? '+' : '').number_format(($ahora - $antes) * 100 / $antes, 0, ',', '.').' % vs. el corte anterior') : null;
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-3">
        {{-- El corte de horas en curso: la cifra principal del tablero. --}}
        @if ($cutoff)
            <x-fronda.hero
                class="lg:col-span-2"
                :label="'Horas extras del corte · '.$cutoff['from']->format('d/m').' al '.$cutoff['to']->format('d/m/Y')"
                :value="$cutoff['overtime']"
                :decimals="1"
                suffix=" h"
                :badge="$cutoff['daysLeft'] === 0 ? 'Hoy es el día de corte' : 'Faltan '.$cutoff['daysLeft'].' '.($cutoff['daysLeft'] === 1 ? 'día' : 'días').' para el corte'"
                icon="heroicon-o-clock"
            >
                <div class="space-y-4">
                    <x-fronda.progress
                        light
                        :percent="$cutoff['elapsedDays'] * 100 / max(1, $cutoff['totalDays'])"
                        :label="'Día '.$cutoff['elapsedDays'].' de '.$cutoff['totalDays']"
                    />
                    <x-fronda.progress
                        light
                        :percent="$cutoff['confirmedPercent']"
                        :label="'Días confirmados: '.$cutoff['confirmed'].' de '.($cutoff['confirmed'] + $cutoff['proposed'])"
                    />
                </div>

                <x-slot name="stats">
                    <x-fronda.hero-stat
                        label="Contra el corte anterior"
                        :value="$cutoff['previousOvertime'] > 0 ? (($cutoff['overtime'] >= $cutoff['previousOvertime'] ? '+' : '').number_format(($cutoff['overtime'] - $cutoff['previousOvertime']) * 100 / $cutoff['previousOvertime'], 0, ',', '.').' %') : '—'"
                        :detail="$cutoff['previousOvertime'] > 0 ? $horas($cutoff['previousOvertime']).' h de extras a esta altura' : 'Sin corte anterior para comparar'"
                    />
                    <x-fronda.hero-stat
                        label="Horas con recargo"
                        :value="$horas($cutoff['surcharges']).' h'"
                        :detail="$cambio($cutoff['surcharges'], $cutoff['previousSurcharges']) ?? 'Sin corte anterior para comparar'"
                    />
                </x-slot>
            </x-fronda.hero>
        @endif

        {{-- Accesos rápidos a lo que se hace a diario. --}}
        <div @class([
            'flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900',
            'lg:col-span-3' => ! $cutoff,
        ])>
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/5">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Accesos rápidos</p>
                <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5 text-gray-400" />
            </div>
            <div class="grid flex-1 grid-cols-2 content-start gap-2 p-3">
                @foreach ($shortcuts as $atajo)
                    <a href="{{ $atajo['url'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:text-gray-200 dark:hover:bg-primary-500/10 dark:hover:text-primary-300">
                        <x-filament::icon :icon="$atajo['icon']" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span>{{ $atajo['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Lo que requiere atención hoy: cada tarjeta abre la lista ya filtrada. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($cards as $card)
            <x-fronda.kpi
                :label="$card['label']"
                :value="number_format($card['value'], 0, ',', '.')"
                :detail="$card['detail']"
                :icon="$card['icon']"
                :tone="$card['tone']"
                :href="$card['url']"
            />
        @endforeach
    </div>

    {{--
        El indicador «factor de horas» con los renglones del Excel de talento humano
        (TH-INF-F-002), de lo que va del corte. Solo lo ve quien ve la nómina.
    --}}
    @if ($factor)
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
                Como el TH-INF-F-002 de Talento Humano: del {{ $factor['from']->format('d/m/Y') }} a hoy. Lo ordinario es el sueldo del mes de cada grupo; «con recargos» suma recargos y horas extras, y «sin recargos» son solo las horas extras.
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
                                    <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $marca($fila['label']) }}"></span>{{ $fila['label'] }}</span>
                                </td>
                                <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums text-gray-400 dark:border-white/5">—</td>
                                <td class="border-b border-gray-100 px-3 py-2 text-right tabular-nums dark:border-white/5">{{ $fila['amount'] > 0 ? $pesos($fila['amount']) : '—' }}</td>
                            </tr>
                        @endforeach

                        @foreach ($factor['withSurcharges'] as $fila)
                            <tr>
                                <td class="border-b border-gray-100 px-3 py-2 dark:border-white/5">
                                    <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $marca($fila['label']) }}"></span>{{ $fila['label'] }}</span>
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
                                    <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $marca($fila['label']) }}"></span>{{ $fila['label'] }}</span>
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
                Desde el corte de octubre de 2026, MOPE va dentro de Mantenimiento, como en el Excel.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
