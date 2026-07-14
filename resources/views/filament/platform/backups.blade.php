<x-filament-panels::page>

    @if (empty($backups))
        {{-- No hay respaldo. No es un estado neutro: es una emergencia. --}}
        <div class="rounded-xl border border-red-200 bg-red-50 p-6 shadow-sm dark:border-red-800 dark:bg-red-900/20">
            <p class="font-semibold text-red-800 dark:text-red-300">No hay ningún respaldo</p>
            <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                El disco «{{ $disk }}» está vacío. Si la base se pierde hoy, se pierde todo: los equipos,
                las órdenes, el histórico de paros. Respalda ahora y revisa por qué la tarea programada
                de la 1:00 a. m. no está corriendo.
            </p>
        </div>
    @else
        @php $latest = $backups[0]; @endphp

        <div class="rounded-xl border p-5 shadow-sm
            {{ $latest['age_hours'] > 36
                ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
                : 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20' }}">
            <p class="text-base font-semibold text-gray-900 dark:text-white">
                Último respaldo:
                {{ $latest['age_hours'] < 1 ? 'hace menos de una hora' : 'hace ' . $latest['age_hours'] . ' h' }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $latest['created_at']->format('d/m/Y H:i') }} · {{ $latest['size_mb'] }} MB · disco «{{ $disk }}»
            </p>
            @if ($latest['age_hours'] > 36)
                <p class="mt-2 text-sm font-medium text-red-700 dark:text-red-400">
                    El respaldo diario no se está ejecutando.
                </p>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="p-3 text-left font-semibold">Archivo</th>
                        <th class="p-3 text-left font-semibold">Fecha</th>
                        <th class="p-3 text-right font-semibold">Tamaño</th>
                        <th class="p-3 text-right font-semibold">Antigüedad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="p-3 font-mono text-xs text-gray-900 dark:text-white">{{ $backup['name'] }}</td>
                            <td class="p-3 text-gray-700 dark:text-gray-300">{{ $backup['created_at']->format('d/m/Y H:i') }}</td>
                            <td class="p-3 text-right text-gray-700 dark:text-gray-300">{{ $backup['size_mb'] }} MB</td>
                            <td class="p-3 text-right text-gray-500 dark:text-gray-400">{{ $backup['age_hours'] }} h</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</x-filament-panels::page>
