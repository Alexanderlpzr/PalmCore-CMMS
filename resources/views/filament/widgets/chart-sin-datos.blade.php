{{-- Una gráfica sin datos en el período (DiceCuandoNoHayDatos): su título y qué falta. --}}
<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section :heading="$heading" :description="$description">
        <div class="flex flex-col items-center justify-center gap-3 py-8 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-500/10">
                <x-filament::icon icon="heroicon-o-chart-bar" class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            </span>
            <p class="max-w-xs text-sm text-gray-500 dark:text-gray-400">{{ $mensaje }}</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
