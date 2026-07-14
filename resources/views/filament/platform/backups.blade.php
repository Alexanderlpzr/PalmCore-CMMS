<x-filament-panels::page>

    {{-- El estado del interruptor, dicho sin adornos --}}
    @if (! $automaticEnabled)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-800 dark:bg-amber-900/20">
            <p class="font-semibold text-amber-900 dark:text-amber-200">Respaldo automático desactivado</p>
            <p class="mt-1 text-sm text-amber-800 dark:text-amber-300">
                No se están guardando copias de la base de datos. Si el servidor se pierde, se pierde
                todo lo que no hayas respaldado a mano.
                @if ($changedBy && $changedBy['user'])
                    Lo desactivó {{ $changedBy['user'] }} el {{ $changedBy['at']->format('d/m/Y H:i') }}.
                @endif
            </p>
        </div>
    @endif

    @if (empty($backups))
        <div class="rounded-xl border p-6 shadow-sm
            {{ $automaticEnabled
                ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
                : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' }}">
            <p class="font-semibold {{ $automaticEnabled ? 'text-red-800 dark:text-red-300' : 'text-gray-900 dark:text-white' }}">
                No hay ningún respaldo
            </p>
            <p class="mt-1 text-sm {{ $automaticEnabled ? 'text-red-700 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                @if ($automaticEnabled)
                    El disco «{{ $disk }}» está vacío y el respaldo automático está encendido: eso significa
                    que la tarea nocturna no se está ejecutando. Respalda ahora y revisa por qué.
                @else
                    El respaldo automático está apagado, así que no hay copias. Puedes crear una cuando
                    quieras con «Respaldar ahora».
                @endif
            </p>
        </div>
    @else
        @php $latest = $backups[0]; @endphp

        <div class="rounded-xl border p-5 shadow-sm
            {{ $automaticEnabled && $latest['age_hours'] > 36
                ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
                : 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20' }}">
            <p class="text-base font-semibold text-gray-900 dark:text-white">
                Último respaldo:
                {{ $latest['age_hours'] < 1 ? 'hace menos de una hora' : 'hace ' . $latest['age_hours'] . ' h' }}
            </p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $latest['created_at']->format('d/m/Y H:i') }} · {{ $latest['size_mb'] }} MB · disco «{{ $disk }}»
            </p>
            @if ($automaticEnabled && $latest['age_hours'] > 36)
                <p class="mt-2 text-sm font-medium text-red-700 dark:text-red-400">
                    El respaldo diario no se está ejecutando.
                </p>
            @endif
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="p-3 text-left font-semibold">Archivo</th>
                        <th class="p-3 text-left font-semibold">Fecha</th>
                        <th class="p-3 text-right font-semibold">Tamaño</th>
                        <th class="p-3 text-right font-semibold">Antigüedad</th>
                        <th class="p-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($backups as $backup)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="p-3 font-mono text-xs text-gray-900 dark:text-white">{{ $backup['name'] }}</td>
                            <td class="p-3 text-gray-700 dark:text-gray-300">{{ $backup['created_at']->format('d/m/Y H:i') }}</td>
                            <td class="p-3 text-right text-gray-700 dark:text-gray-300">{{ $backup['size_mb'] }} MB</td>
                            <td class="p-3 text-right text-gray-500 dark:text-gray-400">{{ $backup['age_hours'] }} h</td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-2">
                                    {{ ($this->downloadAction)(['name' => $backup['name']]) }}
                                    {{ ($this->deleteAction)(['name' => $backup['name']]) }}
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Un respaldo que vive en el mismo servidor que la base no protege de perder el servidor. --}}
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Estas copias están en el mismo servidor que la base de datos. Te protegen de un borrado
            accidental, no de perder la máquina: descarga las que te importen.
        </p>
    @endif

    <x-filament-actions::modals />

</x-filament-panels::page>
