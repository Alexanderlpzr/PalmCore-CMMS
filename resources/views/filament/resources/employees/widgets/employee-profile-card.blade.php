<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-white/10 dark:bg-gray-900">
        {{-- Quién es --}}
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-600 text-lg font-bold text-white">
                {{ $initials ?: '?' }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate text-lg font-semibold text-gray-950 dark:text-white">{{ $employee->fullName() }}</p>
                    <x-filament::badge :color="$status->color()">{{ $status->label() }}</x-filament::badge>
                    @if ($novelty)
                        <x-filament::badge color="info" icon="heroicon-o-information-circle">{{ $novelty }}</x-filament::badge>
                    @endif
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ collect([$employee->position, $area, $employee->document_number ? 'C.C. '.$employee->document_number : null])->filter()->implode(' · ') }}
                </p>
                @if ($employee->immediate_supervisor)
                    <p class="text-xs text-gray-500 dark:text-gray-400">Jefe inmediato: {{ $employee->immediate_supervisor }}</p>
                @endif
            </div>
        </div>

        {{-- En qué está --}}
        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
            @foreach ($tiles as $tile)
                <div @class([
                    'rounded-lg border p-3',
                    'border-gray-200 dark:border-white/10' => $tile['tone'] === 'neutral',
                    'border-success-300 bg-success-50/50 dark:border-success-500/30 dark:bg-success-500/5' => $tile['tone'] === 'ok',
                    'border-warning-300 bg-warning-50/50 dark:border-warning-500/30 dark:bg-warning-500/5' => $tile['tone'] === 'warning',
                    'border-danger-300 bg-danger-50/50 dark:border-danger-500/30 dark:bg-danger-500/5' => $tile['tone'] === 'danger',
                ])>
                    <div class="flex items-center gap-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-filament::icon :icon="$tile['icon']" class="h-4 w-4" />
                        {{ $tile['label'] }}
                    </div>
                    <p class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $tile['value'] }}</p>
                    @if ($tile['detail'])
                        <p @class([
                            'text-xs',
                            'text-gray-500 dark:text-gray-400' => in_array($tile['tone'], ['neutral', 'ok']),
                            'text-warning-700 dark:text-warning-400' => $tile['tone'] === 'warning',
                            'text-danger-700 dark:text-danger-400' => $tile['tone'] === 'danger',
                        ])>{{ $tile['detail'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
