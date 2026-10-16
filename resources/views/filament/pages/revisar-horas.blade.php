<x-filament-panels::page>
    {{-- La semana: navegar, buscar y el resumen con la leyenda de colores. --}}
    <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center gap-2">
            <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="semanaAnterior" label="Semana anterior" color="gray" />
            <p class="min-w-44 text-center text-base font-semibold text-gray-950 dark:text-white">{{ $weekLabel }}</p>
            <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="semanaSiguiente" label="Semana siguiente" color="gray" />
            @unless ($isCurrentWeek)
                <x-filament::button size="xs" color="gray" wire:click="estaSemana">Esta semana</x-filament::button>
            @endunless
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-filament::input.wrapper class="w-56">
                <x-filament::input type="search" wire:model.live.debounce.400ms="buscar" placeholder="Buscar trabajador" />
            </x-filament::input.wrapper>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <x-filament::input.checkbox wire:model.live="soloPendientes" />
                Solo con días por confirmar
            </label>
        </div>
    </div>

    {{-- Cómo va la semana: lo que falta, lo que tiene avisos y lo ya firmado. --}}
    @php
        $totalSemana = $counts['pending'] + $counts['confirmed'];
    @endphp
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
        <x-fronda.kpi
            label="Por confirmar"
            :value="$counts['pending']"
            icon="heroicon-o-clipboard-document-check"
            :tone="$counts['pending'] > 0 ? 'warning' : 'ok'"
            :detail="$counts['pending'] > 0 ? 'Toque un día amarillo para revisarlo' : 'Nada pendiente esta semana'"
        />
        <x-fronda.kpi
            label="Con avisos"
            :value="$counts['warnings']"
            icon="heroicon-o-exclamation-triangle"
            :tone="$counts['warnings'] > 0 ? 'danger' : 'ok'"
            :detail="$counts['warnings'] > 0 ? 'Marcas sin cerrar o turnos raros' : 'Sin avisos'"
        />
        <x-fronda.kpi
            class="col-span-2 lg:col-span-1"
            label="Confirmados"
            :value="$counts['confirmed']"
            :color="\App\Support\FrondaPalette::Brand[600]"
            :percent="$totalSemana > 0 ? $counts['confirmed'] * 100 / $totalSemana : 0"
            :detail="'De '.$totalSemana.' '.($totalSemana === 1 ? 'día' : 'días').' de la semana'"
        />
    </div>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-600 dark:text-gray-300">
        @foreach (['pending' => ['bg-warning-100 ring-warning-300 dark:bg-warning-500/20', 'Por confirmar'], 'warning' => ['bg-danger-100 ring-danger-300 dark:bg-danger-500/20', 'Con avisos'], 'confirmed' => ['bg-success-100 ring-success-300 dark:bg-success-500/20', 'Confirmado'], 'adjusted' => ['bg-info-100 ring-info-300 dark:bg-info-500/20', 'Ajustado a mano']] as [$clase, $texto])
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded ring-1 {{ $clase }}"></span>{{ $texto }}</span>
        @endforeach
    </div>

    {{-- La cuadrícula: un trabajador por fila, un día por columna. Tocar un día lo abre. --}}
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <table class="w-full min-w-[880px] text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="sticky left-0 z-10 bg-white px-4 py-3 text-left font-semibold text-gray-700 dark:bg-gray-900 dark:text-gray-200">Trabajador</th>
                    @foreach ($dates as $fecha)
                        <th @class([
                            'px-2 py-3 text-center font-semibold',
                            'text-primary-700 dark:text-primary-400' => $fecha->toDateString() === $today,
                            'text-gray-700 dark:text-gray-200' => $fecha->toDateString() !== $today,
                        ])>
                            <span class="block text-xs font-medium uppercase tracking-wide text-gray-500">{{ $fecha->locale('es')->translatedFormat('D') }}</span>
                            {{ $fecha->format('d/m') }}
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-200">Semana</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $fila)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-white/5" wire:key="fila-{{ $fila['employee']->id }}">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2 dark:bg-gray-900">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $fila['employee']->fullName() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fila['employee']->position ?? $fila['employee']->document_number }}</p>
                        </td>
                        @foreach ($fila['cells'] as $celda)
                            <td class="px-1.5 py-1.5 text-center">
                                @if ($celda)
                                    <button
                                        type="button"
                                        wire:click="mountAction('dia', { day: '{{ $celda['id'] }}' })"
                                        @class([
                                            'w-full rounded-lg px-2 py-2 ring-1 transition hover:scale-[1.03] hover:shadow',
                                            'bg-warning-50 ring-warning-300 text-warning-900 dark:bg-warning-500/15 dark:text-warning-200' => $celda['tone'] === 'pending',
                                            'bg-danger-50 ring-danger-300 text-danger-900 dark:bg-danger-500/15 dark:text-danger-200' => $celda['tone'] === 'warning',
                                            'bg-success-50 ring-success-300 text-success-900 dark:bg-success-500/15 dark:text-success-200' => $celda['tone'] === 'confirmed',
                                            'bg-info-50 ring-info-300 text-info-900 dark:bg-info-500/15 dark:text-info-200' => $celda['tone'] === 'adjusted',
                                        ])
                                    >
                                        <span class="block font-semibold tabular-nums">{{ $celda['hours'] }}</span>
                                        @if ($celda['extra'] !== '')
                                            <span class="block text-[11px] opacity-80">+{{ $celda['extra'] }} extra</span>
                                        @endif
                                    </button>
                                @else
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-4 py-2 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fila['total'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center">
                            <p class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $soloPendientes ? 'No hay días por confirmar en esta semana' : 'Nadie trabajó esta semana' }}
                            </p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                @if ($soloPendientes)
                                    Quite «Solo con días por confirmar» para ver la semana completa, o pase a otra semana.
                                @else
                                    Pase a otra semana con las flechas de arriba.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
