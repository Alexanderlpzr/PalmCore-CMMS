@php
    $tabs = [];

    // El Control de Mantenimiento es la vista principal: va primero.
    if ($this->controlTabVisible()) {
        $tabs['control'] = [
            'label' => 'Control de Mantenimiento',
            'icon' => 'heroicon-m-wrench-screwdriver',
            'on' => 'bg-emerald-600 text-white shadow-sm',
            'off' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20',
        ];
    }

    $tabs['diario'] = [
        'label' => 'Registro Diario',
        'icon' => 'heroicon-m-calendar-days',
        'on' => 'bg-blue-600 text-white shadow-sm',
        'off' => 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20',
    ];

    $tabs['semanal'] = [
        'label' => 'Registro Semanal',
        'icon' => 'heroicon-m-calendar',
        'on' => 'bg-amber-500 text-white shadow-sm',
        'off' => 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20',
    ];

    $tabs['horas'] = [
        'label' => 'Horas Trabajadas',
        'icon' => 'heroicon-m-clock',
        'on' => 'bg-violet-600 text-white shadow-sm',
        'off' => 'bg-violet-50 text-violet-700 hover:bg-violet-100 dark:bg-violet-500/10 dark:text-violet-300 dark:hover:bg-violet-500/20',
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

    @if ($this->onControlTab())
        @include('filament.resources.meter-readings.partials.maintenance-control', ['groups' => $this->controlGroups()])
    @elseif ($tab === 'horas')
        @include('filament.resources.meter-readings.partials.worked-hours', $this->workedHoursReport())
    @else
        {{-- Sub-vista: capturar la ronda del período (cómoda) o la cuadrícula de varios
             días (para revisar y corregir). --}}
        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
            @foreach (['lista' => ['Capturar', 'heroicon-m-pencil-square'], 'cuadricula' => ['Cuadrícula', 'heroicon-m-table-cells']] as $key => $opt)
                <button
                    type="button"
                    wire:click="setRoundView('{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition',
                        'bg-white text-gray-900 shadow-sm dark:bg-white/10 dark:text-white' => $roundView === $key,
                        'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $roundView !== $key,
                    ])
                >
                    <x-filament::icon :icon="$opt[1]" class="h-4 w-4" />
                    {{ $opt[0] }}
                </button>
            @endforeach
        </div>

        @if ($roundView === 'cuadricula')
            @include('filament.resources.meter-readings.partials.matrix', $this->getMatrixData())
        @else
            @include('filament.resources.meter-readings.partials.round', $this->getRoundData())
        @endif
    @endif

    {{-- La página es HasTable, pero ninguna pestaña renderiza {{ $this->table }}, así
         que <x-filament-panels::page> no aporta el contenedor de modales. Sin esto los
         modales de las acciones del encabezado —Configurar equipos, Agregar tarea,
         Registrar ronda, Registrar lectura— se montan pero no tienen dónde aparecer. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
