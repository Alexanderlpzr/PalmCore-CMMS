<x-filament-widgets::widget>
    @php
        $columnas = count($tiles) + ($bars ? 2 : 0);
    @endphp

    <div @class([
        'grid grid-cols-2 gap-3',
        'md:grid-cols-3 xl:grid-cols-6' => $columnas >= 6,
        'lg:grid-cols-5' => $columnas === 5,
        'lg:grid-cols-4' => $columnas === 4,
        'lg:grid-cols-3' => $columnas <= 3,
    ])>
        @foreach ($tiles as $tile)
            <x-fronda.kpi
                :label="$tile['label']"
                :value="$tile['value']"
                :detail="$tile['detail'] ?? null"
                :icon="$tile['icon'] ?? null"
                :color="$tile['color'] ?? null"
                :tone="$tile['tone'] ?? 'neutral'"
                :percent="$tile['percent'] ?? null"
                :href="$tile['href']"
                :active="$tile['active']"
            />
        @endforeach

        {{-- Las barras: cada columna es un período, apilada por serie; al pasar el ratón dice las cifras. --}}
        @if ($bars)
            @php
                $totales = collect($bars['labels'])->keys()->map(fn (int $i): float => (float) collect($bars['series'])->sum(fn (array $s): float => (float) ($s['values'][$i] ?? 0)));
                $tope = max(1, (float) $totales->max());
            @endphp
            <div class="col-span-2 flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $bars['title'] }}</p>
                    <div class="flex flex-wrap gap-x-3 gap-y-1">
                        @foreach ($bars['series'] as $serie)
                            <span class="flex items-center gap-1 text-[11px] text-gray-500"><span class="h-2 w-2 rounded-full" style="background-color: {{ $serie['color'] }}"></span>{{ $serie['label'] }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="flex h-20 items-end gap-1.5">
                    @foreach ($bars['labels'] as $i => $etiqueta)
                        @php
                            $detalle = collect($bars['series'])->map(fn (array $s): string => $s['label'].': '.rtrim(rtrim(number_format((float) ($s['values'][$i] ?? 0), 1, ',', '.'), '0'), ','))->implode(' · ');
                        @endphp
                        <div class="group flex h-full flex-1 flex-col items-center justify-end" title="{{ $etiqueta }} — {{ $detalle }}">
                            <div class="flex w-full flex-col-reverse overflow-hidden rounded-t" style="height: {{ round($totales[$i] * 100 / $tope) }}%">
                                @foreach ($bars['series'] as $serie)
                                    @php($valor = (float) ($serie['values'][$i] ?? 0))
                                    @if ($valor > 0 && $totales[$i] > 0)
                                        <div class="w-full transition group-hover:opacity-80" style="height: {{ $valor * 100 / $totales[$i] }}%; background-color: {{ $serie['color'] }}"></div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1.5">
                    @foreach ($bars['labels'] as $etiqueta)
                        <span class="flex-1 truncate text-center text-[10px] text-gray-500">{{ $etiqueta }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
