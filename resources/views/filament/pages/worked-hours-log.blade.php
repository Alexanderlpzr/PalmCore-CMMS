<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <div class="w-full max-w-xs">
            <label class="fi-fo-field-wrp-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                Tipo de registro
            </label>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="viewMode">
                    <option value="diario">Registro diario</option>
                    <option value="semanal">Registro semanal</option>
                    <option value="mensual">Registro mensual</option>
                    <option value="anual">Registro anual</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        @unless ($this->isPeriodEntryMode())
            @if ($viewMode === 'mensual')
                <div class="w-full max-w-[10rem]">
                    <label class="fi-fo-field-wrp-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Mes
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="month">
                            @foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $i => $label)
                                <option value="{{ $i + 1 }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            @endif

            <div class="w-full max-w-[8rem]">
                <label class="fi-fo-field-wrp-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Año
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="year">
                        @foreach (range(now()->year, now()->year - 5) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        @endunless
    </div>

    @if ($this->isPeriodEntryMode())
        {{ $this->table }}
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <table class="fi-ta-table w-full text-start">
                <thead class="divide-y divide-gray-200 dark:divide-white/10">
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white">Equipo</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-end text-sm font-semibold text-gray-950 dark:text-white">Horas totales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse ($summary as $row)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell px-3 py-4 text-sm text-gray-950 dark:text-white">
                                {{ $row['code'] }} — {{ $row['name'] }}
                            </td>
                            <td class="fi-ta-cell px-3 py-4 text-end text-sm text-gray-950 dark:text-white">
                                {{ number_format($row['total_hours'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Sin horas registradas en este período.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
