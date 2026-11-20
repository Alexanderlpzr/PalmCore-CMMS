<x-filament-panels::page>
    @if ($summary['total'] === 0)
        <x-fronda.empty-state icon="heroicon-o-shield-exclamation" heading="Todavía no hay hallazgos">
            Reporte el primero con su foto, su ubicación y las áreas que lo corrigen, o importe el cuadro de Excel desde la lista de hallazgos.
        </x-fronda.empty-state>
    @else
        <x-fronda.hero
            label="Hallazgos abiertos"
            :value="$summary['open']"
            :badge="$summary['total'] === 1 ? '1 hallazgo reportado' : $summary['total'].' hallazgos reportados'"
            icon="heroicon-o-shield-exclamation"
        >
            <x-fronda.progress light :percent="$summary['corregidosPercent']" :label="($summary['total'] - $summary['open']).' de '.$summary['total'].' corregidos'" />

            <x-slot:stats>
                <x-fronda.hero-stat label="Prioridad alta abiertos" :value="$summary['altaAbiertos']" detail="Plazo de 8 días" />
                <x-fronda.hero-stat label="Vencidos" :value="$summary['vencidos']" detail="Fuera de plazo" />
                <x-fronda.hero-stat label="Por verificar" :value="$summary['porVerificar']" detail="Esperan la revisión de SST" />
            </x-slot:stats>
        </x-fronda.hero>

        {{-- Cada tarjeta abre la lista ya filtrada. --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-5">
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
            Lo demás, en pestañas (2026-11-19): los vencidos, cómo va cada área, dónde están
            y de quién son. Cada área y cada sección despliega sus hallazgos al tocarla.
        --}}
        @php
            $fila = function ($hallazgo) use ($findingUrl, $today): string {
                $dias = (int) $hallazgo->due_on->diffInDays($today);
                $vencido = $hallazgo->isOverdue($today);

                return '<a href="'.e($findingUrl($hallazgo)).'" class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 hover:bg-gray-50 dark:hover:bg-white/5">'
                    .'<span class="min-w-0"><span class="block truncate text-sm font-medium text-gray-950 dark:text-white">'.e($hallazgo->code().' · '.$hallazgo->origin->label().' · '.($hallazgo->description ?: 'Hallazgo con foto')).'</span>'
                    .'<span class="block truncate text-xs text-gray-500 dark:text-gray-400">'.e($hallazgo->locationLabel().' · prioridad '.mb_strtolower($hallazgo->priority->label())).'</span></span>'
                    .'<span class="shrink-0 text-xs font-semibold tabular-nums '.($vencido ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400').'">'
                    .e($vencido ? 'vencido hace '.$dias.' '.($dias === 1 ? 'día' : 'días') : 'vence el '.$hallazgo->due_on->format('d/m')).'</span></a>';
            };
        @endphp

        <x-fronda.pestanas :pestanas="['vencidos' => 'Vencidos ('.$summary['vencidos'].')', 'areas' => 'Por área', 'secciones' => 'Por sección y prioridad', 'origen' => 'SST y Ambiental']">
            {{-- Los más atrasados primero. --}}
            <x-fronda.pestana nombre="vencidos">
                <x-filament::section heading="Vencidos" description="Los hallazgos abiertos que ya pasaron su plazo, del más atrasado al más reciente.">
                    @forelse ($summary['overdueList'] as $hallazgo)
                        {!! $fila($hallazgo) !!}
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Ningún hallazgo vencido. 👍</p>
                    @endforelse
                </x-filament::section>
            </x-fronda.pestana>

            {{-- Cómo va cada área con lo que le toca; al tocarla, lo que le falta. --}}
            <x-fronda.pestana nombre="areas">
                <x-filament::section heading="Por área responsable" description="La parte de cada área: corregida sobre el total que le tocó. Toque un área para ver lo que le falta.">
                    <div class="space-y-2" x-data="{ abierta: null }">
                        @foreach ($summary['byArea'] as $indice => $area)
                            <div data-area="{{ $area['label'] }}">
                                <button type="button" class="w-full rounded-lg p-2 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                                        x-on:click="abierta = abierta === {{ $indice }} ? null : {{ $indice }}"
                                        @disabled($area['findings']->isEmpty())>
                                    <x-fronda.progress
                                        :percent="$area['percent']"
                                        :label="$area['label'].' · '.$area['done'].' de '.$area['total']"
                                        :tone="$area['overdue'] > 0 ? 'danger' : 'primary'"
                                    />
                                    <p class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $area['open'] }} {{ $area['open'] === 1 ? 'pendiente' : 'pendientes' }}@if ($area['overdue'] > 0) · <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $area['overdue'] }} {{ $area['overdue'] === 1 ? 'vencido' : 'vencidos' }}</span>@endif
                                        @if ($area['findings']->isNotEmpty())
                                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 transition" x-bind:class="abierta === {{ $indice }} ? 'rotate-180' : ''" />
                                        @endif
                                    </p>
                                </button>
                                @if ($area['findings']->isNotEmpty())
                                    <div x-show="abierta === {{ $indice }}" x-collapse x-cloak class="ms-2 border-s-2 border-primary-200 ps-3 dark:border-primary-500/30">
                                        @foreach ($area['findings'] as $hallazgo)
                                            {!! $fila($hallazgo) !!}
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            </x-fronda.pestana>

            {{-- Dónde están y con qué urgencia. --}}
            <x-fronda.pestana nombre="secciones">
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-filament::section heading="Abiertos por sección" description="Toque una sección para ver sus hallazgos.">
                        @if ($summary['bySection'] !== [])
                            @php($maximo = max(array_column($summary['bySection'], 'open')))
                            <div class="space-y-1" x-data="{ abierta: null }">
                                @foreach ($summary['bySection'] as $indice => $seccion)
                                    <div>
                                        <button type="button" class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-white/5"
                                                x-on:click="abierta = abierta === {{ $indice }} ? null : {{ $indice }}">
                                            <span class="w-32 shrink-0 truncate text-gray-600 dark:text-gray-300" title="{{ $seccion['label'] }}">{{ $seccion['label'] }}</span>
                                            <span class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-white/10">
                                                <span class="block h-2 rounded-full bg-primary-500" style="width: {{ max(4, round($seccion['open'] / $maximo * 100)) }}%"></span>
                                            </span>
                                            <span class="w-6 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $seccion['open'] }}</span>
                                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 shrink-0 text-gray-400 transition" x-bind:class="abierta === {{ $indice }} ? 'rotate-180' : ''" />
                                        </button>
                                        <div x-show="abierta === {{ $indice }}" x-collapse x-cloak class="ms-2 border-s-2 border-primary-200 ps-3 dark:border-primary-500/30">
                                            @foreach ($seccion['findings'] as $hallazgo)
                                                {!! $fila($hallazgo) !!}
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400">Ningún hallazgo abierto.</p>
                        @endif
                    </x-filament::section>

                    <x-filament::section heading="Abiertos por prioridad" description="El plazo se cuenta desde la fecha de reporte.">
                        <div class="grid grid-cols-3 gap-3">
                            @foreach ($summary['byPriority'] as $prioridad)
                                <div @class([
                                    'rounded-xl border p-3 text-center',
                                    'border-danger-200 bg-danger-50 dark:border-danger-500/30 dark:bg-danger-500/10' => $prioridad['color'] === 'danger',
                                    'border-warning-200 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/10' => $prioridad['color'] === 'warning',
                                    'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5' => $prioridad['color'] === 'gray',
                                ])>
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $prioridad['label'] }} · {{ $prioridad['days'] }} días</p>
                                    <p class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $prioridad['open'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $prioridad['overdue'] }} {{ $prioridad['overdue'] === 1 ? 'vencido' : 'vencidos' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                </div>
            </x-fronda.pestana>

            {{-- SST y Ambiental por separado: de quién es cada hallazgo. --}}
            <x-fronda.pestana nombre="origen">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($summary['byOrigin'] as $origen)
                        <a href="{{ $originUrl($origen['origin']) }}" class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5">
                            <x-filament::icon :icon="$origen['icon']" class="h-8 w-8 shrink-0 text-primary-600 dark:text-primary-400" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $origen['label'] }} · {{ $origen['total'] }} {{ $origen['total'] === 1 ? 'hallazgo' : 'hallazgos' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $origen['open'] }} {{ $origen['open'] === 1 ? 'abierto' : 'abiertos' }} · {{ $origen['porVerificar'] }} por verificar @if ($origen['overdue'] > 0) · <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $origen['overdue'] }} {{ $origen['overdue'] === 1 ? 'vencido' : 'vencidos' }}</span>@endif
                                </p>
                            </div>
                            <p class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $origen['open'] }}</p>
                        </a>
                    @endforeach
                </div>
            </x-fronda.pestana>
        </x-fronda.pestanas>
    @endif
</x-filament-panels::page>
