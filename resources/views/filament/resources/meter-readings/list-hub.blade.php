@php
    $tabs = [
        'diario' => [
            'label' => 'Registro Diario',
            'icon' => 'heroicon-m-calendar-days',
            'on' => 'bg-blue-600 text-white shadow-sm',
            'off' => 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20',
        ],
        'semanal' => [
            'label' => 'Registro Semanal',
            'icon' => 'heroicon-m-calendar',
            'on' => 'bg-amber-500 text-white shadow-sm',
            'off' => 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20',
        ],
        'historial' => [
            'label' => 'Historial',
            'icon' => 'heroicon-m-clock',
            'on' => 'bg-slate-700 text-white shadow-sm',
            'off' => 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10',
        ],
    ];
@endphp

<x-filament-panels::page>
    {{-- Pestañas de color: un solo lugar para todo lo de horómetros --}}
    <div class="flex flex-wrap gap-2">
        @foreach ($tabs as $key => $t)
            <button
                type="button"
                wire:click="selectTab('{{ $key }}')"
                @class([
                    'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition',
                    $t['on'] => $tab === $key,
                    $t['off'] => $tab !== $key,
                ])
            >
                <x-filament::icon :icon="$t['icon']" class="h-4 w-4" />
                {{ $t['label'] }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'historial')
        {{ $this->table }}
    @else
        @include('filament.resources.meter-readings.partials.matrix', $this->getMatrixData())
    @endif
</x-filament-panels::page>
