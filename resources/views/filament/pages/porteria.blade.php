<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-5">
        {{-- La puerta: cámara, resultado y el campo del lector --}}
        <div class="flex flex-col gap-4 lg:col-span-3">
            <x-filament::section>
                {{-- `wire:ignore`: cada marca repinta la pantalla y no debe apagar el video. --}}
                <div wire:ignore class="flex flex-col gap-3">
                    <div id="porteria-camara" class="w-full overflow-hidden rounded-xl bg-gray-900" style="min-height: 240px;"></div>
                    <p id="porteria-camara-aviso" hidden class="rounded-lg bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"></p>
                </div>

                <form wire:submit="marcarDelCampo" class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <x-filament::input.wrapper class="flex-1">
                        <x-filament::input
                            id="porteria-token"
                            type="text"
                            wire:model="token"
                            placeholder="Lector USB o código del carné"
                            autocomplete="off"
                            autofocus
                        />
                    </x-filament::input.wrapper>
                    <x-filament::button type="submit" icon="heroicon-o-check">
                        Marcar
                    </x-filament::button>
                </form>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Con lector USB, deje el cursor en este campo: lee el carné y marca solo.
                </p>
            </x-filament::section>

            @if ($ultimo)
                <div @class([
                    'rounded-xl border-2 p-5',
                    'border-success-500 bg-success-50 dark:bg-success-500/10' => $ultimo['entrada'],
                    'border-info-500 bg-info-50 dark:bg-info-500/10' => ! $ultimo['entrada'],
                ])>
                    <p @class([
                        'text-sm font-bold uppercase tracking-wider',
                        'text-success-700 dark:text-success-400' => $ultimo['entrada'],
                        'text-info-700 dark:text-info-400' => ! $ultimo['entrada'],
                    ])>
                        {{ $ultimo['sentido'] }} · {{ $ultimo['hora'] }}
                    </p>
                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $ultimo['nombre'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $ultimo['documento'] }}@if ($ultimo['cargo']) · {{ $ultimo['cargo'] }}@endif
                    </p>
                    @if ($ultimo['aviso'])
                        <p class="mt-2 text-sm font-medium text-warning-700 dark:text-warning-400">{{ $ultimo['aviso'] }}</p>
                    @endif
                </div>
            @endif

            @if ($error)
                <div class="rounded-xl border-2 border-danger-500 bg-danger-50 p-4 text-sm font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                    {{ $error }}
                </div>
            @endif
        </div>

        {{-- Lo del día --}}
        <div class="flex flex-col gap-4 lg:col-span-2">
            <x-filament::section>
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Adentro ahora</p>
                    <p class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $adentro }}</p>
                </div>
            </x-filament::section>

            <x-filament::section :heading="'Hoy · ' . $marcas->count() . ' ' . ($marcas->count() === 1 ? 'marca' : 'marcas')">
                @forelse ($marcas as $marca)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $marca->employee?->fullName() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $marca->employee?->document_number }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p @class([
                                'text-xs font-bold',
                                'text-success-600 dark:text-success-400' => $marca->isEntry(),
                                'text-info-600 dark:text-info-400' => ! $marca->isEntry(),
                            ])>{{ $marca->direction->label() }}</p>
                            <p class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $marca->scanned_at->copy()->setTimezone($timezone)->format('h:i a') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Todavía nadie ha marcado hoy.</p>
                @endforelse
            </x-filament::section>
        </div>
    </div>

    @if ($scriptUrl)
        <script type="module" src="{{ $scriptUrl }}"></script>
    @endif
</x-filament-panels::page>
