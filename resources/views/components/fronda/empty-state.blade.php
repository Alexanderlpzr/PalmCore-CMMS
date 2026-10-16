{{--
    Lo que se ve cuando todavía no hay nada que mostrar: un ícono, qué pasa y qué hacer.
    Un tablero vacío no es un error, y tiene que decir cuándo se va a llenar.
--}}
@props(['icon' => 'heroicon-o-inbox', 'heading'])

<div {{ $attributes->class('flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-white/15 dark:bg-gray-900') }}>
    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-500/10">
        <x-filament::icon :icon="$icon" class="h-8 w-8 text-primary-600 dark:text-primary-400" />
    </span>
    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $heading }}</p>
    @if ($slot->isNotEmpty())
        <div class="max-w-md text-sm text-gray-500 dark:text-gray-400">{{ $slot }}</div>
    @endif
</div>
