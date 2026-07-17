@php
    $badge = [
        'danger' => ['bg' => 'bg-red-50 dark:bg-red-500/10', 'ring' => 'ring-red-600/20 dark:ring-red-400/30', 'text' => 'text-red-700 dark:text-red-400', 'dot' => 'bg-red-500'],
        'warning' => ['bg' => 'bg-amber-50 dark:bg-amber-500/10', 'ring' => 'ring-amber-600/20 dark:ring-amber-400/30', 'text' => 'text-amber-700 dark:text-amber-500', 'dot' => 'bg-amber-500'],
        'info' => ['bg' => 'bg-blue-50 dark:bg-blue-500/10', 'ring' => 'ring-blue-600/20 dark:ring-blue-400/30', 'text' => 'text-blue-700 dark:text-blue-400', 'dot' => 'bg-blue-500'],
    ];
@endphp

<x-filament-panels::page>
    @if (empty($findings))
        <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
            <x-filament::icon icon="heroicon-o-check-badge" class="h-12 w-12 text-emerald-500" />
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Todo en orden</h2>
            <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                No se encontraron problemas de integridad en los datos de mantenimiento de esta empresa.
            </p>
        </div>
    @else
        <div class="mb-2 text-sm text-gray-500 dark:text-gray-400">
            Se encontraron {{ count($findings) }} tipo(s) de hallazgo
            @if ($criticalCount > 0)
                — <span class="font-semibold text-red-600 dark:text-red-400">{{ $criticalCount }} crítico(s)</span> que conviene atender primero.
            @endif
        </div>

        <div class="grid gap-4">
            @foreach ($findings as $finding)
                @php $c = $badge[$finding->severity->value] ?? $badge['info']; @endphp
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="mt-0.5 flex h-2.5 w-2.5 shrink-0 rounded-full {{ $c['dot'] }}"></span>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $finding->title }}</h3>
                                <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">{{ $finding->description }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-lg px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $c['bg'] }} {{ $c['ring'] }} {{ $c['text'] }}">
                            {{ $finding->count }}
                        </span>
                    </div>

                    @if (! empty($finding->sample))
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($finding->sample as $label)
                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $label }}</span>
                            @endforeach
                            @if ($finding->count > count($finding->sample))
                                <span class="rounded-md px-2 py-1 text-xs text-gray-400">+{{ $finding->count - count($finding->sample) }} más</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
