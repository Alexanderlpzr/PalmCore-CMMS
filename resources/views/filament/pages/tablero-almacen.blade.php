<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Lo que vale lo que hay en los almacenes, y qué tan surtido está. --}}
        <x-fronda.hero
            class="lg:col-span-2"
            label="Valor del inventario · al costo promedio"
            :value="$hero['value']"
            prefix="$ "
            :badge="$hero['movementsToday'] === 1 ? '1 movimiento hoy' : $hero['movementsToday'].' movimientos hoy'"
            icon="heroicon-o-archive-box"
        >
            <x-fronda.progress light :percent="$hero['percent']" :label="$hero['progressLabel']" />
        </x-fronda.hero>

        {{-- Accesos rápidos --}}
        <div class="flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/5">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Accesos rápidos</p>
                <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5 text-gray-400" />
            </div>
            <div class="grid flex-1 grid-cols-2 content-start gap-2 p-3">
                @foreach ($shortcuts as $atajo)
                    <a href="{{ $atajo['url'] }}" class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-3 text-sm font-medium text-gray-700 transition hover:border-primary-500 hover:bg-primary-50 hover:text-primary-700 dark:border-white/10 dark:text-gray-200 dark:hover:bg-primary-500/10 dark:hover:text-primary-300">
                        <x-filament::icon :icon="$atajo['icon']" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span>{{ $atajo['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Lo que requiere atención: cada tarjeta abre la lista ya filtrada. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-5">
        @foreach ($cards as $card)
            <x-fronda.kpi
                :label="$card['label']"
                :value="$card['value']"
                :detail="$card['detail']"
                :icon="$card['icon']"
                :tone="$card['tone']"
                :href="$card['url']"
            />
        @endforeach
    </div>

    {{-- Los últimos movimientos --}}
    <x-filament::section heading="Últimos movimientos" description="Use los botones de arriba para registrar una entrada, una salida o un conteo.">
        @forelse ($movements as $movimiento)
            @php($cantidad = (float) $movimiento->quantity)
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $movimiento->spare_part_name_snapshot }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $movimiento->exit_reason?->label() ?? $movimiento->type->label() }} · {{ $movimiento->warehouse?->name }}@if ($movimiento->recipient_name) · Recibió {{ $movimiento->recipient_name }}@endif · {{ $movimiento->performed_at?->copy()->setTimezone($timezone)->format('d/m h:i a') }}
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

    {{-- El indicador de talento humano (TH-INF-F-002): solo para quien ve la nómina. --}}
    @if ($showFactor)
        @livewire(\App\Livewire\HumanResources\HoursFactorIndicator::class, ['tenantId' => $tenantId])
    @endif
</x-filament-panels::page>
