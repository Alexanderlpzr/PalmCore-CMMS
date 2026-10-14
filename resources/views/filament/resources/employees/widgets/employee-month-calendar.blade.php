<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-gray-900">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="mesAnterior" label="Mes anterior" color="gray" />
                <p class="min-w-40 text-center text-base font-semibold text-gray-950 dark:text-white">{{ $label }}</p>
                <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="mesSiguiente" label="Mes siguiente" color="gray" />
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($summary as $nombre => $valor)
                    <span>{{ $nombre }}: <strong class="tabular-nums text-gray-950 dark:text-white">{{ $valor }}</strong></span>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
            @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dia)
                <div class="py-1">{{ $dia }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-1">
            @foreach ($cells as $celda)
                <div
                    @if ($celda['holiday']) title="{{ $celda['holiday'] }}" @endif
                    @class([
                        'min-h-16 rounded-lg p-1.5 text-left ring-1',
                        'opacity-40' => ! $celda['inMonth'],
                        'ring-2 ring-primary-500' => $celda['isToday'],
                        'bg-warning-50 ring-warning-300 dark:bg-warning-500/15' => $celda['tone'] === 'pending',
                        'bg-danger-50 ring-danger-300 dark:bg-danger-500/15' => $celda['tone'] === 'warning',
                        'bg-success-50 ring-success-300 dark:bg-success-500/15' => $celda['tone'] === 'confirmed',
                        'bg-info-50 ring-info-300 dark:bg-info-500/15' => $celda['tone'] === 'adjusted',
                        'bg-purple-50 ring-purple-300 dark:bg-purple-500/15' => ! $celda['tone'] && $celda['novelty'],
                        'ring-gray-100 dark:ring-white/5' => ! $celda['tone'] && ! $celda['novelty'] && ! $celda['isToday'],
                    ])
                >
                    <p @class([
                        'text-xs font-semibold',
                        'text-danger-600 dark:text-danger-400' => $celda['surcharged'],
                        'text-gray-500 dark:text-gray-400' => ! $celda['surcharged'],
                    ])>{{ $celda['date']->day }}</p>
                    @if ($celda['hours'])
                        <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $celda['hours'] }}</p>
                        @if ($celda['extra'] !== '')
                            <p class="text-[11px] text-gray-600 dark:text-gray-300">+{{ $celda['extra'] }} extra</p>
                        @endif
                    @endif
                    @if ($celda['novelty'])
                        <p class="truncate text-[11px] font-medium text-purple-700 dark:text-purple-300">{{ $celda['novelty'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
            @foreach (['bg-warning-100 ring-warning-300' => 'Por confirmar', 'bg-danger-100 ring-danger-300' => 'Con avisos', 'bg-success-100 ring-success-300' => 'Confirmado', 'bg-info-100 ring-info-300' => 'Ajustado a mano', 'bg-purple-100 ring-purple-300' => 'Novedad'] as $clase => $texto)
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded ring-1 {{ $clase }}"></span>{{ $texto }}</span>
            @endforeach
            <span class="text-danger-600">Día en rojo: domingo o festivo</span>
        </div>
    </div>
</x-filament-widgets::widget>
