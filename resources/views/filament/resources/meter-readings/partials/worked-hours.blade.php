{{-- Horas trabajadas por equipo, consolidadas del horómetro (suma de deltas).
     Espera: $mode, $rows, $total, $periodLabel, $years, $months --}}
<div class="mt-4 space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <select
                wire:model.live="whMode"
                class="rounded-lg border-gray-300 bg-white py-1.5 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
            >
                <option value="mensual">Mensual</option>
                <option value="anual">Anual</option>
            </select>

            @if ($mode === 'mensual')
                <select
                    wire:model.live="whMonth"
                    class="rounded-lg border-gray-300 bg-white py-1.5 text-sm capitalize dark:border-white/10 dark:bg-white/5 dark:text-white"
                >
                    @foreach ($months as $num => $name)
                        <option value="{{ $num }}">{{ ucfirst($name) }}</option>
                    @endforeach
                </select>
            @endif

            <select
                wire:model.live="whYear"
                class="rounded-lg border-gray-300 bg-white py-1.5 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
            >
                @foreach ($years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            Calculado del horómetro: la suma de horas trabajadas de cada equipo en el período.
        </p>
    </div>

    @if (empty($rows))
        <div class="rounded-xl border border-dashed border-gray-300 py-16 text-center dark:border-gray-700">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                No hay lecturas de horómetro en <span class="font-semibold capitalize">{{ $periodLabel }}</span>.
            </p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Equipo</th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-700 capitalize dark:text-gray-200">Horas · {{ $periodLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="px-3 py-1.5">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $row['code'] }}</span>
                                <span class="text-gray-400">— {{ $row['name'] }}</span>
                            </td>
                            <td class="px-3 py-1.5 text-right font-semibold text-gray-900 tabular-nums dark:text-white">
                                {{ number_format($row['total_hours'], 0) }} h
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                        <td class="px-3 py-2 font-semibold text-gray-700 dark:text-gray-200">Total</td>
                        <td class="px-3 py-2 text-right font-bold text-violet-700 tabular-nums dark:text-violet-400">
                            {{ number_format($total, 0) }} h
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
