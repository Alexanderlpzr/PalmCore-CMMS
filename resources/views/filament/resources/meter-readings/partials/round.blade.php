{{-- Ronda de un período: captura cómoda, una fila por equipo, ideal en celular.
     Espera: $periodKey, $periodLabel, $rows, $pending, $total, $isDaily, $canGoNext --}}
<div class="mt-4 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previousPeriod" />
            <x-filament::button color="gray" size="sm" wire:click="goToToday">{{ $isDaily ? 'Hoy' : 'Esta semana' }}</x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="nextPeriod" :disabled="! $canGoNext" />
            <span class="ml-1 text-sm font-medium text-gray-700 capitalize dark:text-gray-200">{{ $periodLabel }}</span>
        </div>
        @if ($total > 0)
            <span @class([
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $pending === 0,
                'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => $pending > 0,
            ])>
                @if ($pending === 0)
                    <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" /> Ronda completa
                @else
                    {{ $total - $pending }}/{{ $total }} leídos · faltan {{ $pending }}
                @endif
            </span>
        @endif
    </div>

    @if (empty($rows))
        <div class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No hay equipos configurados como
                <span class="font-semibold">{{ $isDaily ? 'Registro Diario' : 'Registro Semanal' }}</span>.
                Usa <span class="font-semibold">Configurar equipos</span> para armar la ronda.
            </p>
        </div>
    @else
        <div class="divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
            @foreach ($rows as $row)
                <div @class([
                    'flex items-center gap-3 px-4 py-3',
                    'bg-emerald-50/40 dark:bg-emerald-500/5' => $row['filled'],
                ])>
                    <div class="min-w-0 flex-1">
                        <div class="truncate">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $row['code'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">— {{ $row['name'] }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            @if ($row['reference'] !== null)
                                Última: <span class="tabular-nums">{{ number_format($row['reference'], 0) }}</span> h
                                @if ($row['reference_ago']) · {{ $row['reference_ago'] }} @endif
                            @else
                                Sin lecturas previas
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($row['filled'])
                            <span class="hidden text-xs font-medium text-emerald-600 tabular-nums sm:inline dark:text-emerald-400">
                                +{{ number_format($row['hours'], 0) }} h
                                @if ($row['reset'])
                                    <span title="Cambio de dial (reset)" class="text-amber-600">⟳</span>
                                @endif
                            </span>
                            <input
                                type="number"
                                inputmode="decimal"
                                step="0.1"
                                min="0"
                                wire:model="editDraft.{{ $row['reading_id'] }}"
                                wire:keydown.enter="saveEditedReading('{{ $row['reading_id'] }}')"
                                wire:blur="saveEditedReading('{{ $row['reading_id'] }}')"
                                title="Corrige el valor y Enter. Vacíalo para borrar la lectura."
                                class="w-28 rounded-lg border-emerald-300 bg-white px-3 py-2 text-right text-base font-semibold text-gray-900 tabular-nums focus:border-emerald-500 focus:ring-emerald-500 dark:border-emerald-500/40 dark:bg-white/5 dark:text-white"
                            />
                        @else
                            <input
                                type="number"
                                inputmode="decimal"
                                step="0.1"
                                min="0"
                                wire:model="draft.{{ $row['id'] }}.{{ $periodKey }}"
                                wire:keydown.enter="saveCell('{{ $row['id'] }}', '{{ $periodKey }}')"
                                wire:blur="saveCell('{{ $row['id'] }}', '{{ $periodKey }}')"
                                placeholder="Horómetro"
                                class="w-28 rounded-lg border-gray-300 bg-white px-3 py-2 text-right text-base tabular-nums focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            />
                        @endif
                        <span class="text-xs text-gray-400">h</span>
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Escribe el horómetro y presiona Enter. El sistema calcula las horas trabajadas solo.
            Para corregir una lectura ya cargada, edítala; para borrarla, vacía el campo.
        </p>
    @endif
</div>
