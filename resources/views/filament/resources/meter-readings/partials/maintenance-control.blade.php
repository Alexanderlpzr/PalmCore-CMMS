{{-- Control de Mantenimiento por horómetros, calcado del Excel de la planta:
     una fila por tarea, agrupadas por equipo, con el semáforo de vencimiento.
     Espera: $groups (list de ['equipment' => [...], 'rows' => [...]]) --}}
@php
    $canWrite = $this->canWriteControl();
    $canOt = $this->canCreateOtFromControl();

    $badge = [
        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'danger' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
        'gray' => 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-300',
    ];
@endphp

<div class="mt-4 space-y-4">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Con tiempo
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Dentro del aviso — arma la OT
        </span>
        <span class="inline-flex items-center gap-1.5">
            <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span> Vencido
        </span>
        <span class="ml-auto">El horómetro actual lo alimentan el Registro Diario y Semanal.</span>
    </div>

    @if (empty($groups))
        <div class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aún no hay tareas de mantenimiento por horómetro.
                @if ($canWrite)
                    Usa <span class="font-semibold">Agregar tarea</span> para crear la primera.
                @endif
            </p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-200">Equipo / Tarea</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Frecuencia</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Últ. mtto</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Actual</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Próximo</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Faltan</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Días</th>
                        <th class="px-2 py-2 text-center font-semibold text-gray-600 dark:text-gray-300">Aviso</th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        @php $eq = $group['equipment']; @endphp
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                            <td colspan="8" class="sticky left-0 z-10 bg-gray-50 px-3 py-1.5 dark:bg-white/5">
                                <span class="font-bold text-gray-900 dark:text-white">{{ $eq['code'] }}</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">— {{ $eq['name'] }}</span>
                            </td>
                            <td class="bg-gray-50 px-3 py-1.5 text-right text-xs text-gray-500 tabular-nums dark:bg-white/5 dark:text-gray-400">
                                Actual: {{ number_format($eq['current'], 0) }} h
                            </td>
                        </tr>

                        @foreach ($group['rows'] as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                {{-- Tarea: en negrilla si es del equipo entero; indentada si es de pieza --}}
                                <td class="sticky left-0 z-10 bg-white px-3 py-1.5 dark:bg-gray-900">
                                    <div @class([
                                        'pl-3' => ! $row['is_equipment_level'],
                                    ])>
                                        <span @class([
                                            'text-gray-900 dark:text-white',
                                            'font-semibold' => $row['is_equipment_level'],
                                        ])>{{ $row['task'] }}</span>
                                        @if ($row['component'])
                                            <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-500 dark:bg-white/10 dark:text-gray-400">{{ $row['component'] }}</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Frecuencia (editable) --}}
                                <td class="px-2 py-1 text-center">
                                    @if ($canWrite)
                                        <input
                                            type="number" inputmode="numeric" min="1" step="1"
                                            wire:model="controlDraft.{{ $row['plan_id'] }}.meter_interval"
                                            wire:keydown.enter="saveControlCell('{{ $row['plan_id'] }}', 'meter_interval')"
                                            wire:blur="saveControlCell('{{ $row['plan_id'] }}', 'meter_interval')"
                                            class="w-20 rounded-md border-gray-300 bg-white px-1.5 py-1 text-center text-sm tabular-nums focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                        />
                                    @else
                                        <span class="tabular-nums text-gray-700 dark:text-gray-200">{{ number_format((float) $row['frequency'], 0) }} h</span>
                                    @endif
                                </td>

                                {{-- Horómetro último mtto (editable) --}}
                                <td class="px-2 py-1 text-center">
                                    @if ($canWrite)
                                        <input
                                            type="number" inputmode="decimal" min="0" step="0.1"
                                            wire:model="controlDraft.{{ $row['plan_id'] }}.last_completed_meter"
                                            wire:keydown.enter="saveControlCell('{{ $row['plan_id'] }}', 'last_completed_meter')"
                                            wire:blur="saveControlCell('{{ $row['plan_id'] }}', 'last_completed_meter')"
                                            placeholder="0"
                                            class="w-20 rounded-md border-gray-300 bg-white px-1.5 py-1 text-center text-sm tabular-nums focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                        />
                                    @else
                                        <span class="tabular-nums text-gray-700 dark:text-gray-200">{{ $row['last_meter'] !== null ? number_format((float) $row['last_meter'], 0) : '—' }}</span>
                                    @endif
                                </td>

                                {{-- Actual (solo lectura) --}}
                                <td class="px-2 py-1 text-center font-medium text-gray-900 tabular-nums dark:text-white">
                                    {{ number_format($row['current'], 0) }}
                                </td>

                                {{-- Próximo (solo lectura) --}}
                                <td class="px-2 py-1 text-center text-gray-700 tabular-nums dark:text-gray-200">
                                    {{ $row['next'] !== null ? number_format($row['next'], 0) : '—' }}
                                </td>

                                {{-- Faltan (semáforo) --}}
                                <td class="px-2 py-1 text-center">
                                    @if ($row['remaining'] === null)
                                        <span class="text-gray-400">—</span>
                                    @else
                                        <span @class(['inline-block rounded-md px-2 py-0.5 text-xs font-semibold tabular-nums', $badge[$row['color']]])>
                                            @if ($row['remaining'] <= 0)
                                                Vencido {{ number_format($row['remaining'], 0) }} h
                                            @else
                                                {{ number_format($row['remaining'], 0) }} h
                                            @endif
                                        </span>
                                    @endif
                                </td>

                                {{-- Días faltantes --}}
                                <td class="px-2 py-1 text-center text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ $row['days'] !== null ? $row['days'] : '—' }}
                                </td>

                                {{-- Aviso / umbral (editable) --}}
                                <td class="px-2 py-1 text-center">
                                    @if ($canWrite)
                                        <input
                                            type="number" inputmode="numeric" min="0" step="1"
                                            wire:model="controlDraft.{{ $row['plan_id'] }}.meter_lead_hours"
                                            wire:keydown.enter="saveControlCell('{{ $row['plan_id'] }}', 'meter_lead_hours')"
                                            wire:blur="saveControlCell('{{ $row['plan_id'] }}', 'meter_lead_hours')"
                                            title="Horas antes del vencimiento en que la fila pasa a ámbar"
                                            class="w-16 rounded-md border-gray-300 bg-white px-1.5 py-1 text-center text-sm tabular-nums focus:border-emerald-500 focus:ring-emerald-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                        />
                                    @else
                                        <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $row['lead'] }} h</span>
                                    @endif
                                </td>

                                {{-- Acciones --}}
                                <td class="px-3 py-1 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        @if ($row['has_open_ot'])
                                            <span class="inline-flex items-center gap-1 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                                                <x-filament::icon icon="heroicon-m-wrench" class="h-3.5 w-3.5" /> OT abierta
                                            </span>
                                        @elseif ($canOt && $row['can_create_ot'])
                                            <x-filament::button
                                                size="xs"
                                                :color="$row['color'] === 'danger' ? 'danger' : 'warning'"
                                                icon="heroicon-m-clipboard-document-list"
                                                wire:click="crearOt('{{ $row['plan_id'] }}')"
                                                wire:confirm="¿Crear la OT preventiva de esta tarea?"
                                            >OT</x-filament::button>
                                        @endif

                                        @if ($canWrite)
                                            <x-filament::button
                                                size="xs"
                                                color="gray"
                                                icon="heroicon-m-check-circle"
                                                wire:click="registrarMantenimiento('{{ $row['plan_id'] }}')"
                                                wire:confirm="Registrar que este mantenimiento se hizo ahora, al horómetro actual del equipo. ¿Continuar?"
                                            >Hecho</x-filament::button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
