<x-filament-panels::page>
    {{-- Lo que requiere atención hoy: cada tarjeta abre la lista ya filtrada. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($cards as $card)
            <a
                href="{{ $card['url'] }}"
                @class([
                    'group flex flex-col justify-between gap-2 rounded-xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900',
                    'border-gray-200 dark:border-white/10' => in_array($card['tone'], ['neutral', 'ok']),
                    'border-warning-300 dark:border-warning-500/40' => $card['tone'] === 'warning',
                    'border-danger-300 dark:border-danger-500/40' => $card['tone'] === 'danger',
                ])
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $card['label'] }}</p>
                    <x-filament::icon
                        :icon="$card['icon']"
                        @class([
                            'h-5 w-5',
                            'text-gray-400' => $card['tone'] === 'neutral',
                            'text-success-600' => $card['tone'] === 'ok',
                            'text-warning-600' => $card['tone'] === 'warning',
                            'text-danger-600' => $card['tone'] === 'danger',
                        ])
                    />
                </div>
                <p @class([
                    'text-3xl font-bold tabular-nums',
                    'text-gray-950 dark:text-white' => in_array($card['tone'], ['neutral', 'ok']),
                    'text-warning-600 dark:text-warning-400' => $card['tone'] === 'warning',
                    'text-danger-600 dark:text-danger-400' => $card['tone'] === 'danger',
                ])>{{ number_format($card['value'], 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 group-hover:text-primary-600 dark:text-gray-400">{{ $card['detail'] }} →</p>
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-5">
        {{-- El corte de horas en curso --}}
        @if ($cutoff)
            @php
                $avance = min(100, (int) round($cutoff['elapsedDays'] * 100 / max(1, $cutoff['totalDays'])));
                $cambio = fn (float $ahora, float $antes): ?string => $antes > 0 ? (($ahora >= $antes ? '+' : '') . number_format(($ahora - $antes) * 100 / $antes, 0, ',', '.') . ' % vs. el corte anterior') : null;
                $horas = fn (float $h): string => rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',');
            @endphp
            <x-filament::section class="lg:col-span-3"
                :heading="'Corte en curso · ' . $cutoff['from']->format('d/m') . ' al ' . $cutoff['to']->format('d/m/Y')"
                :description="$cutoff['daysLeft'] === 0 ? 'Hoy es el día de corte.' : 'Faltan ' . $cutoff['daysLeft'] . ' ' . ($cutoff['daysLeft'] === 1 ? 'día' : 'días') . ' para el corte.'"
            >
                <div class="space-y-5">
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Día {{ $cutoff['elapsedDays'] }} de {{ $cutoff['totalDays'] }}</span>
                            <span>{{ $avance }} %</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-primary-600" style="width: {{ $avance }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>Días confirmados: {{ $cutoff['confirmed'] }} de {{ $cutoff['confirmed'] + $cutoff['proposed'] }}</span>
                            <span>{{ $cutoff['confirmedPercent'] }} %</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div @class([
                                'h-full rounded-full',
                                'bg-success-600' => $cutoff['confirmedPercent'] >= 90,
                                'bg-warning-500' => $cutoff['confirmedPercent'] < 90,
                            ]) style="width: {{ $cutoff['confirmedPercent'] }}%"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Horas extras</p>
                            <p class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $horas($cutoff['overtime']) }} h</p>
                            @if ($texto = $cambio($cutoff['overtime'], $cutoff['previousOvertime']))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $texto }}</p>
                            @endif
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Horas con recargo</p>
                            <p class="text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $horas($cutoff['surcharges']) }} h</p>
                            @if ($texto = $cambio($cutoff['surcharges'], $cutoff['previousSurcharges']))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $texto }}</p>
                            @endif
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Incluye los días por confirmar. La comparación es contra el corte anterior a la misma altura.</p>
                </div>
            </x-filament::section>
        @endif

        {{-- Accesos rápidos --}}
        <x-filament::section @class(['lg:col-span-2' => $cutoff, 'lg:col-span-5' => ! $cutoff]) heading="Accesos rápidos">
            <div class="grid grid-cols-2 gap-2">
                @foreach ($shortcuts as $atajo)
                    <a href="{{ $atajo['url'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:text-gray-200 dark:hover:bg-primary-500/10 dark:hover:text-primary-300">
                        <x-filament::icon :icon="$atajo['icon']" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span>{{ $atajo['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
