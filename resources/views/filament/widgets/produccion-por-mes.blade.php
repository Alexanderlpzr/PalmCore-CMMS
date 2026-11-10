@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $maxHeight = $this->getMaxHeight();
    $hasMaxHeight = filled($maxHeight) && $maxHeight !== '100%';
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        @if ($filters || method_exists($this, 'getFiltersSchema'))
            <x-slot name="afterHeader">
                @if ($filters)
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter"
                        class="fi-wi-chart-filter"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filter"
                        >
                            @foreach ($filters as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif

                @if (method_exists($this, 'getFiltersSchema'))
                    <x-filament::dropdown
                        placement="bottom-end"
                        shift
                        width="xs"
                        class="fi-wi-chart-filter"
                    >
                        <x-slot name="trigger">
                            {{ $this->getFiltersTriggerAction() }}
                        </x-slot>

                        <div class="fi-wi-chart-filter-content">
                            {{ $this->getFiltersSchema() }}

                            @if (method_exists($this, 'hasDeferredFilters') && $this->hasDeferredFilters())
                                <div
                                    class="fi-wi-chart-filter-content-actions-ctn"
                                >
                                    {{ $this->getFiltersApplyAction() }}

                                    {{ $this->getFiltersResetAction() }}
                                </div>
                            @endif
                        </div>
                    </x-filament::dropdown>
                @endif
            </x-slot>
        @endif

        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                {{
                    (new ComponentAttributeBag)
                        ->color(ChartWidgetComponent::class, $color)
                        ->class([
                            'fi-wi-chart-canvas-ctn',
                            'fi-wi-chart-canvas-ctn-no-aspect-ratio' => $hasMaxHeight,
                        ])
                }}
            >
                <canvas
                    x-ref="canvas"
                    @style([
                        'width: 100%',
                        'height: 100%; max-height: 100%' => ! $hasMaxHeight,
                        "max-height: {$maxHeight}" => $hasMaxHeight,
                    ])
                ></canvas>

                <span
                    x-ref="backgroundColorElement"
                    class="fi-wi-chart-bg-color"
                ></span>

                <span
                    x-ref="borderColorElement"
                    class="fi-wi-chart-border-color"
                ></span>

                <span
                    x-ref="gridColorElement"
                    class="fi-wi-chart-grid-color"
                ></span>

                <span
                    x-ref="textColorElement"
                    class="fi-wi-chart-text-color"
                ></span>
            </div>
        </div>

        {{--
            La tabla de la gráfica: la vista de ChartWidget de Filament con las cifras de cada
            mes debajo. Ver App\Filament\Widgets\Executive\MonthlyProductionWidget.
        --}}
        @php
            $comparacion = $this->comparison();
            $toneladas = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 1, ',', '.');
            $horas = fn (?float $valor): string => $valor === null ? '—' : number_format($valor, 1, ',', '.');
        @endphp

        @if ($comparacion['rows'] !== [])
            <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[640px] divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Mes</th>
                            <th class="px-3 py-2 text-right">Toneladas {{ $comparacion['year'] }}</th>
                            <th class="px-3 py-2 text-right">Toneladas {{ $comparacion['previousYear'] }}</th>
                            <th class="px-3 py-2 text-right">Variación</th>
                            <th class="px-3 py-2 text-right">Horas efectivas</th>
                            <th class="px-3 py-2 text-right">t / hora</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($comparacion['rows'] as $fila)
                            <tr @class([
                                'text-gray-400 dark:text-gray-500' => $fila['tons'] === null,
                                'text-gray-950 dark:text-white' => $fila['tons'] !== null,
                                'bg-primary-50/60 dark:bg-primary-500/10' => $comparacion['best'] && $fila['label'] === $comparacion['best']['label'],
                            ])>
                                <td class="px-3 py-2 font-medium">
                                    {{ $fila['label'] }}
                                    @if ($comparacion['best'] && $fila['label'] === $comparacion['best']['label'])
                                        <span class="ms-1 text-xs font-semibold text-primary-600 dark:text-primary-400">· mejor mes</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $toneladas($fila['tons']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $toneladas($fila['previousTons']) }}</td>
                                <td @class([
                                    'px-3 py-2 text-right font-semibold tabular-nums',
                                    'text-success-600 dark:text-success-400' => ($fila['change'] ?? 0) > 0,
                                    'text-danger-600 dark:text-danger-400' => ($fila['change'] ?? 0) < 0,
                                ])>
                                    @if ($fila['change'] === null)
                                        —
                                    @else
                                        {{ $fila['change'] > 0 ? '▲' : ($fila['change'] < 0 ? '▼' : '') }} {{ number_format(abs($fila['change']), 1, ',', '.') }} %
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $horas($fila['effectiveHours']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $fila['tonsPerHour'] === null ? '—' : number_format($fila['tonsPerHour'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($total = $comparacion['total'])
                        <tfoot class="bg-gray-50 font-semibold text-gray-950 dark:bg-white/5 dark:text-white">
                            <tr>
                                <td class="px-3 py-2">
                                    Total
                                    <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">
                                        {{ $total['months'] }} {{ $total['months'] === 1 ? 'mes' : 'meses' }}, contra los mismos de {{ $comparacion['previousYear'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $toneladas($total['tons']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $total['previousTons'] > 0 ? $toneladas($total['previousTons']) : '—' }}</td>
                                <td @class([
                                    'px-3 py-2 text-right tabular-nums',
                                    'text-success-600 dark:text-success-400' => ($total['change'] ?? 0) > 0,
                                    'text-danger-600 dark:text-danger-400' => ($total['change'] ?? 0) < 0,
                                ])>
                                    @if ($total['change'] === null)
                                        —
                                    @else
                                        {{ $total['change'] > 0 ? '▲' : ($total['change'] < 0 ? '▼' : '') }} {{ number_format(abs($total['change']), 1, ',', '.') }} %
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $horas($total['effectiveHours']) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $total['tonsPerHour'] === null ? '—' : number_format($total['tonsPerHour'], 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
