{{--
    Una gráfica que, al tocar una barra o una porción, abre debajo su ficha: la vista de
    ChartWidget de Filament con la ficha al final. El widget usa el trait
    App\Filament\Widgets\Concerns\DespliegaDetalle y dice en detalles() qué lleva cada
    ficha. El clic lo avisa el complemento frondaDetalle (resources/js/filament-chart-js-plugins.js).
--}}
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

        <div x-data="{ sel: null, fichas: @js($this->detalles()) }" x-on:fronda-barra="sel = (sel === $event.detail ? null : $event.detail)" data-detalle>
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
        <p x-show="sel === null && fichas.length" class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
            Toque una barra para ver su detalle.
        </p>

        <template x-if="sel !== null && fichas[sel]">
            <div class="mt-4 rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-500/30 dark:bg-primary-500/10">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="fichas[sel].titulo"></p>
                    <div class="flex items-center gap-3">
                        <template x-if="fichas[sel].url">
                            <a x-bind:href="fichas[sel].url" class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">Abrir →</a>
                        </template>
                        <button type="button" x-on:click="sel = null" class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Cerrar ✕</button>
                    </div>
                </div>
                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <template x-for="cifra in fichas[sel].cifras" x-bind:key="cifra.etiqueta">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400" x-text="cifra.etiqueta"></dt>
                            <dd class="text-base font-bold tabular-nums text-gray-950 dark:text-white" x-text="cifra.valor"></dd>
                        </div>
                    </template>
                </dl>
            </div>
        </template>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
