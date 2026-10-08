<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-5 lg:gap-6">
        {{-- La puerta: cámara con el resultado encima, la última marca y el campo del lector --}}
        <div class="flex flex-col gap-3 lg:col-span-3">
            <x-filament::section>
                {{-- La cámara cuadrada y compacta, para que en el celular quepa todo sin bajar.
                     El resultado se pinta encima: es donde el vigilante ya está mirando. --}}
                <div class="relative mx-auto w-full" style="max-width: min(100%, 46vh);">
                    {{-- `wire:ignore`: cada marca repinta la pantalla y no debe apagar el video. --}}
                    <div wire:ignore>
                        <div id="porteria-camara" class="aspect-square w-full overflow-hidden rounded-xl bg-gray-900"></div>
                        <p id="porteria-camara-aviso" hidden class="rounded-lg bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"></p>
                        <div id="porteria-camara-reintentar" hidden class="mt-3 text-center">
                            <x-filament::button icon="heroicon-o-camera" color="gray" size="sm">
                                Reintentar cámara
                            </x-filament::button>
                        </div>
                    </div>

                    @if ($ultimo || $error)
                        {{-- Un aviso por intento: cuatro segundos encima de la cámara y se va solo. --}}
                        <div
                            wire:key="resultado-{{ $intento }}"
                            x-data="{ visible: true }"
                            x-init="setTimeout(() => visible = false, 4000)"
                            x-show="visible"
                            x-transition.opacity.duration.300ms
                            @click="visible = false"
                            style="min-height: max(100%, 16rem);"
                            @class([
                                // Cubre la cámara; sin cámara, igual se lee grande.
                                'absolute inset-x-0 top-0 z-10 flex min-h-full cursor-pointer flex-col items-center justify-center rounded-xl p-4 text-center text-white shadow-lg',
                                'bg-warning-600' => $ultimo && $ultimo['repetido'],
                                'bg-success-600' => $ultimo && ! $ultimo['repetido'] && $ultimo['entrada'],
                                'bg-info-600' => $ultimo && ! $ultimo['repetido'] && ! $ultimo['entrada'],
                                'bg-danger-600' => $error,
                            ])
                        >
                            @if ($ultimo)
                                <p class="text-lg font-black uppercase tracking-widest">{{ $ultimo['repetido'] ? 'Ya marcó '.$ultimo['sentido'] : $ultimo['sentido'] }}</p>
                                <p class="mt-1 whitespace-nowrap text-5xl font-black tabular-nums leading-none sm:text-7xl">{{ $ultimo['hora'] }}</p>
                                <p class="mt-3 text-2xl font-bold leading-tight">{{ $ultimo['nombre'] }}</p>
                                <p class="text-sm opacity-90">{{ $ultimo['documento'] }}@if ($ultimo['cargo']) · {{ $ultimo['cargo'] }}@endif</p>
                                @if ($ultimo['aviso'])
                                    <p class="mt-3 rounded-lg bg-white/20 px-3 py-1 text-sm font-medium">{{ $ultimo['aviso'] }}</p>
                                @endif
                            @else
                                <p class="text-lg font-black uppercase tracking-widest">No se marcó</p>
                                <p class="mt-3 text-xl font-bold leading-snug">{{ $error }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- La última marca buena se queda a la vista hasta la siguiente. --}}
                @if ($ultimaBuena)
                    <div class="mx-auto mt-3 flex w-full items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5" style="max-width: min(100%, 46vh);">
                        <p class="min-w-0 truncate text-sm text-gray-700 dark:text-gray-200">
                            <span class="text-gray-500 dark:text-gray-400">Última:</span>
                            <span class="font-semibold">{{ $ultimaBuena['nombre'] }}</span>
                        </p>
                        <p @class([
                            'shrink-0 text-sm font-bold tabular-nums',
                            'text-success-600 dark:text-success-400' => $ultimaBuena['entrada'],
                            'text-info-600 dark:text-info-400' => ! $ultimaBuena['entrada'],
                        ])>{{ $ultimaBuena['sentido'] }} {{ $ultimaBuena['hora'] }}</p>
                    </div>
                @endif

                {{-- El lector USB: siempre a la vista en el computador; en el celular, detrás de
                     «Escribir código», porque ahí no hay lector y el campo solo estorba. --}}
                <div x-data="{ abierto: false }" class="mt-3">
                    <button type="button" x-show="! abierto" @click="abierto = true; $nextTick(() => $refs.campo.focus())" class="w-full text-center text-sm font-medium text-primary-600 underline sm:hidden dark:text-primary-400">
                        Escribir código
                    </button>

                    <div :class="abierto ? 'block' : 'hidden sm:block'">
                        <form wire:submit="marcarDelCampo" class="flex flex-col gap-2 sm:flex-row">
                            <x-filament::input.wrapper class="flex-1">
                                <x-filament::input
                                    id="porteria-token"
                                    x-ref="campo"
                                    type="text"
                                    wire:model="token"
                                    placeholder="Lector USB o código del carné"
                                    autocomplete="off"
                                />
                            </x-filament::input.wrapper>
                            <x-filament::button type="submit" icon="heroicon-o-check">
                                Marcar
                            </x-filament::button>
                        </form>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Con lector USB, deje el cursor en este campo: lee el carné y marca solo.
                        </p>
                    </div>
                </div>
            </x-filament::section>
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
