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
</x-filament-panels::page>
