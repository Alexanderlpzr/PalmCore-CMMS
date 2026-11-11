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

        {{-- SST y Ambiental por separado: de quién es cada hallazgo. --}}
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($summary['byOrigin'] as $fila)
                <a href="{{ $originUrl($fila['origin']) }}" class="flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5">
                    <x-filament::icon :icon="$fila['icon']" class="h-8 w-8 shrink-0 text-primary-600 dark:text-primary-400" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $fila['label'] }} · {{ $fila['total'] }} {{ $fila['total'] === 1 ? 'hallazgo' : 'hallazgos' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $fila['open'] }} {{ $fila['open'] === 1 ? 'abierto' : 'abiertos' }} · {{ $fila['porVerificar'] }} por verificar @if ($fila['overdue'] > 0) · <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $fila['overdue'] }} {{ $fila['overdue'] === 1 ? 'vencido' : 'vencidos' }}</span>@endif
                        </p>
                    </div>
                    <p class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $fila['open'] }}</p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- Cómo va cada área con lo que le toca. --}}
            <x-filament::section heading="Por área responsable" description="La parte de cada área: corregida sobre el total que le tocó.">
                <div class="space-y-4">
                    @foreach ($summary['byArea'] as $fila)
                        <div>
                            <x-fronda.progress
                                :percent="$fila['percent']"
                                :label="$fila['label'].' · '.$fila['done'].' de '.$fila['total']"
                                :tone="$fila['overdue'] > 0 ? 'danger' : 'primary'"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $fila['open'] }} {{ $fila['open'] === 1 ? 'pendiente' : 'pendientes' }}@if ($fila['overdue'] > 0) · <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $fila['overdue'] }} {{ $fila['overdue'] === 1 ? 'vencido' : 'vencidos' }}</span>@endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- Los abiertos por prioridad, con su plazo. --}}
            <x-filament::section heading="Abiertos por prioridad" description="El plazo se cuenta desde la fecha de reporte.">
                <div class="grid grid-cols-3 gap-3">
                    @foreach ($summary['byPriority'] as $fila)
                        <div @class([
                            'rounded-xl border p-3 text-center',
                            'border-danger-200 bg-danger-50 dark:border-danger-500/30 dark:bg-danger-500/10' => $fila['color'] === 'danger',
                            'border-warning-200 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/10' => $fila['color'] === 'warning',
                            'border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5' => $fila['color'] === 'gray',
                        ])>
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $fila['label'] }} · {{ $fila['days'] }} días</p>
                            <p class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $fila['open'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['overdue'] }} {{ $fila['overdue'] === 1 ? 'vencido' : 'vencidos' }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($summary['bySection'] !== [])
                    <p class="mb-2 mt-5 text-sm font-semibold text-gray-950 dark:text-white">Abiertos por sección</p>
                    @php($maximo = max(array_column($summary['bySection'], 'open')))
                    <div class="space-y-2">
                        @foreach ($summary['bySection'] as $fila)
                            <div class="flex items-center gap-3 text-sm">
                                <span class="w-32 shrink-0 truncate text-gray-600 dark:text-gray-300" title="{{ $fila['label'] }}">{{ $fila['label'] }}</span>
                                <div class="h-2 flex-1 rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-2 rounded-full bg-primary-500" style="width: {{ max(4, round($fila['open'] / $maximo * 100)) }}%"></div>
                                </div>
                                <span class="w-6 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fila['open'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Los más atrasados primero. --}}
        <x-filament::section heading="Vencidos" description="Los hallazgos abiertos que ya pasaron su plazo, del más atrasado al más reciente.">
            @forelse ($summary['overdueList'] as $hallazgo)
                <a href="{{ $findingUrl($hallazgo) }}" class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $hallazgo->code() }} · {{ $hallazgo->origin->label() }} · {{ $hallazgo->description ?: 'Hallazgo con foto' }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $hallazgo->locationLabel() }} · {{ $hallazgo->tasks->map(fn ($parte) => $parte->area->label())->implode(', ') }} · prioridad {{ mb_strtolower($hallazgo->priority->label()) }}
                        </p>
                    </div>
                    <p class="shrink-0 text-sm font-bold tabular-nums text-danger-600 dark:text-danger-400">
                        {{ (int) $hallazgo->due_on->diffInDays($today) }} {{ (int) $hallazgo->due_on->diffInDays($today) === 1 ? 'día' : 'días' }}
                    </p>
                </a>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Ningún hallazgo vencido. 👍</p>
            @endforelse
        </x-filament::section>
    @endif
</x-filament-panels::page>
