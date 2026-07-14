@php
    $levelClasses = [
        'ERROR' => 'text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-900/30',
        'CRITICAL' => 'text-red-800 bg-red-100 dark:text-red-200 dark:bg-red-900/50',
        'ALERT' => 'text-red-800 bg-red-100 dark:text-red-200 dark:bg-red-900/50',
        'EMERGENCY' => 'text-white bg-red-600',
        'WARNING' => 'text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-900/30',
    ];
@endphp

<x-filament-panels::page>

    @if (empty($entries))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-center shadow-sm dark:border-emerald-800 dark:bg-emerald-900/20">
            <p class="font-semibold text-emerald-800 dark:text-emerald-300">Sin errores recientes</p>
            <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-400">
                Se lee el final del log de la aplicación. Si esperabas ver algo aquí y no aparece,
                revisa que el archivo exista y sea legible: el silencio no siempre es buena señal.
            </p>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($entries as $entry)
                <details class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <summary class="flex cursor-pointer items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $entry['message'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $entry['at']?->format('d/m/Y H:i:s') ?? 'sin fecha' }}
                            </p>
                        </div>
                        <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold {{ $levelClasses[$entry['level']] ?? 'text-gray-600 bg-gray-100' }}">
                            {{ $entry['level'] }}
                        </span>
                    </summary>

                    <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ $entry['context'] }}</pre>
                </details>
            @endforeach
        </div>
    @endif

</x-filament-panels::page>
