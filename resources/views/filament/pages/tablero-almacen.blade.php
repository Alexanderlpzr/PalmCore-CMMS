<x-filament-panels::page>
    {{-- Lo que requiere atención: cada tarjeta abre la lista ya filtrada. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        @foreach ($cards as $card)
            <a
                href="{{ $card['url'] }}"
                @class([
                    'group flex flex-col justify-between gap-2 rounded-xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900',
                    'border-gray-200 dark:border-white/10' => in_array($card['tone'], ['neutral', 'ok']),
                    'border-warning-300 dark:border-warning-500/40' => $card['tone'] === 'warning',
                    'border-danger-300 dark:border-danger-500/40' => $card['tone'] === 'danger',
                ])
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $card['label'] }}</p>
                    <x-filament::icon
                        :icon="$card['icon']"
                        @class([
                            'h-5 w-5',
                            'text-gray-400' => $card['tone'] === 'neutral',
                            'text-success-600' => $card['tone'] === 'ok',
                            'text-warning-600' => $card['tone'] === 'warning',
                            'text-danger-600' => $card['tone'] === 'danger',
                        ])
                    />
                </div>
                <p @class([
                    'truncate text-2xl font-bold tabular-nums xl:text-3xl',
                    'text-gray-950 dark:text-white' => in_array($card['tone'], ['neutral', 'ok']),
                    'text-warning-600 dark:text-warning-400' => $card['tone'] === 'warning',
                    'text-danger-600 dark:text-danger-400' => $card['tone'] === 'danger',
                ])>{{ $card['value'] }}</p>
                <p class="text-xs text-gray-500 group-hover:text-primary-600 dark:text-gray-400">{{ $card['detail'] }} →</p>
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-5">
        {{-- Los últimos movimientos --}}
        <x-filament::section class="lg:col-span-3" heading="Últimos movimientos" description="Use los botones de arriba para registrar una entrada, una salida o un conteo.">
            @forelse ($movements as $movimiento)
                @php($cantidad = (float) $movimiento->quantity)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $movimiento->spare_part_name_snapshot }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $movimiento->type->label() }} · {{ $movimiento->warehouse?->name }} · {{ $movimiento->performed_at?->copy()->setTimezone($timezone)->format('d/m h:i a') }}
                        </p>
                    </div>
                    <p @class([
                        'shrink-0 text-sm font-bold tabular-nums',
                        'text-success-600 dark:text-success-400' => $cantidad > 0,
                        'text-danger-600 dark:text-danger-400' => $cantidad < 0,
                        'text-gray-500' => $cantidad == 0,
                    ])>{{ $cantidad > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($cantidad, 2, ',', '.'), '0'), ',') }}</p>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">Todavía no hay movimientos de inventario.</p>
            @endforelse
        </x-filament::section>

        {{-- Accesos rápidos --}}
        <x-filament::section class="lg:col-span-2" heading="Accesos rápidos">
            <div class="grid grid-cols-2 gap-2">
                @foreach ($shortcuts as $atajo)
                    <a href="{{ $atajo['url'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:text-gray-200 dark:hover:bg-primary-500/10 dark:hover:text-primary-300">
                        <x-filament::icon :icon="$atajo['icon']" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span>{{ $atajo['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
