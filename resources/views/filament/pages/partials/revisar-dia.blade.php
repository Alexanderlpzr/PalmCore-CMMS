@if (! $day)
    <p class="text-sm text-gray-500">Este día ya no existe: pudo anularse o volver a armarse.</p>
@else
    <div class="space-y-5">
        {{-- Estado --}}
        <div @class([
            'flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium ring-1',
            'bg-warning-50 ring-warning-300 text-warning-900 dark:bg-warning-500/15 dark:text-warning-200' => $tone === 'pending',
            'bg-danger-50 ring-danger-300 text-danger-900 dark:bg-danger-500/15 dark:text-danger-200' => $tone === 'warning',
            'bg-success-50 ring-success-300 text-success-900 dark:bg-success-500/15 dark:text-success-200' => $tone === 'confirmed',
            'bg-info-50 ring-info-300 text-info-900 dark:bg-info-500/15 dark:text-info-200' => $tone === 'adjusted',
        ])>
            <span>{{ ['pending' => 'Por confirmar', 'warning' => 'Por confirmar · con avisos', 'confirmed' => 'Confirmado', 'adjusted' => 'Ajustado a mano'][$tone] }}</span>
            <span class="text-lg font-bold tabular-nums">{{ $worked }}</span>
        </div>

        {{-- Avisos --}}
        @if ($day->hasAnomalies())
            <div class="space-y-1 rounded-lg bg-danger-50 p-3 text-sm text-danger-800 dark:bg-danger-500/10 dark:text-danger-200">
                @foreach ($day->anomalies as $aviso)
                    <p>⚠ {{ $aviso }}</p>
                @endforeach
            </div>
        @endif

        {{-- Marcas de la puerta --}}
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Marcas de la portería</p>
            @forelse ($marks as $marca)
                <div class="flex items-center justify-between border-b border-gray-100 py-1.5 text-sm last:border-0 dark:border-white/5">
                    <span @class([
                        'font-medium',
                        'text-success-700 dark:text-success-400' => $marca['entry'],
                        'text-info-700 dark:text-info-400' => ! $marca['entry'],
                    ])>{{ $marca['entry'] ? 'Entrada' : 'Salida' }}{{ $marca['manual'] ? ' (a mano)' : '' }}</span>
                    <span class="tabular-nums text-gray-950 dark:text-white">{{ $marca['time'] }} <span class="text-xs text-gray-400">{{ $marca['date'] }}</span></span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin marcas vigentes.</p>
            @endforelse
            @if ($adjustedTimes)
                <p class="mt-2 text-xs text-info-700 dark:text-info-300">Se paga con el ajuste: {{ $adjustedTimes }}.</p>
            @endif
        </div>

        {{-- Horas --}}
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Horas</p>
            @forelse ($hours as $fila)
                <div class="flex items-center justify-between py-1 text-sm">
                    <span class="text-gray-600 dark:text-gray-300">{{ $fila['label'] }}</span>
                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $fila['value'] }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">El día no tiene horas.</p>
            @endforelse
        </div>

        @if ($day->isManuallyAdjusted() && $day->adjustment_reason)
            <p class="text-xs text-gray-500">Motivo del ajuste: {{ $day->adjustment_reason }}</p>
        @endif
    </div>
@endif
