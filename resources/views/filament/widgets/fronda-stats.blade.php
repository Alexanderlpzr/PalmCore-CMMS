{{--
    Las tarjetas de indicadores de mantenimiento con el mismo estilo de Talento Humano: la
    cifra principal del tablero en la tarjeta con degradado (si el widget la da con
    `frondaHero()`) y las demás como x-fronda.kpi, con su color, su barra cuando la cifra
    es un porcentaje y el enlace a donde se resuelve.

    Sirve para cualquier StatsOverviewWidget: basta con `protected string $view =
    'filament.widgets.fronda-stats'`. Lee los mismos Stat que ya calcula el widget, así que
    las cuentas no se duplican.
--}}
@use('App\Support\FrondaPalette')

@php
    $hero = method_exists($this, 'frondaHero') ? $this->frondaHero() : null;
    $replaced = $hero['replaces'] ?? [];

    $colores = [
        'success' => FrondaPalette::Brand[600],
        'primary' => FrondaPalette::Petrol[600],
        'info' => FrondaPalette::Petrol[400],
        'warning' => '#d97706',
        'danger' => '#dc2626',
    ];

    $kpis = collect($this->getCachedStats())
        ->reject(fn ($stat): bool => in_array((string) $stat->getLabel(), $replaced, true))
        ->map(function ($stat) use ($colores): array {
            $color = $stat->getColor();
            $color = is_string($color) ? $color : null;
            $valor = (string) $stat->getValue();
            // Una cifra en porcentaje lleva su barra: se lee de un vistazo cuánto falta.
            $porcentaje = preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*%/u', $valor, $m) ? min(100.0, (float) str_replace(',', '.', $m[1])) : null;

            return [
                'label' => (string) $stat->getLabel(),
                'value' => $valor,
                'detail' => $stat->getDescription(),
                'color' => $colores[$color] ?? null,
                'tone' => in_array($color, ['warning', 'danger'], true) ? $color : 'neutral',
                'percent' => $porcentaje,
                'href' => $stat->getUrl(),
            ];
        })
        ->values();

    // Con una o dos tarjetas pequeñas, van al lado de la grande; sin ninguna, la grande ocupa todo.
    $alLado = $hero && $kpis->isNotEmpty() && $kpis->count() <= 2;
@endphp

<x-filament-widgets::widget class="fi-wi-fronda-stats">
    <div @class(['grid gap-4', 'lg:grid-cols-3' => $alLado])>
        @if ($hero)
            <x-fronda.hero
                :class="$alLado ? 'lg:col-span-2' : ''"
                :label="$hero['label']"
                :value="$hero['value'] ?? 0"
                :missing="($hero['value'] ?? null) === null"
                :prefix="$hero['prefix'] ?? ''"
                :suffix="$hero['suffix'] ?? ''"
                :decimals="$hero['decimals'] ?? 0"
                :badge="$hero['badge'] ?? null"
                :icon="$hero['icon'] ?? 'heroicon-o-chart-bar'"
            >
                @if (isset($hero['empty']))
                    <p class="text-sm text-white/80">{{ $hero['empty'] }}</p>
                @endif
                @if (isset($hero['percent']))
                    <x-fronda.progress light :percent="$hero['percent']" :label="$hero['progressLabel'] ?? null" />
                @endif

                @if (! empty($hero['stats']))
                    <x-slot name="stats">
                        @foreach ($hero['stats'] as $dato)
                            <x-fronda.hero-stat :label="$dato['label']" :value="$dato['value']" :detail="$dato['detail'] ?? null" />
                        @endforeach
                    </x-slot>
                @endif
            </x-fronda.hero>
        @endif

        @if ($kpis->isNotEmpty())
            <div @class([
                'grid gap-3',
                'content-start' => $alLado,
                'grid-cols-2' => ! $alLado,
                // Escritas enteras: Tailwind solo compila las clases que encuentra literales.
                'lg:grid-cols-2' => ! $alLado && $kpis->count() === 2,
                'lg:grid-cols-3' => ! $alLado && $kpis->count() === 3,
                'lg:grid-cols-4' => ! $alLado && $kpis->count() >= 4,
            ])>
                @foreach ($kpis as $kpi)
                    <x-fronda.kpi
                        :label="$kpi['label']"
                        :value="$kpi['value']"
                        :detail="$kpi['detail']"
                        :color="$kpi['color']"
                        :tone="$kpi['tone']"
                        :percent="$kpi['percent']"
                        :href="$kpi['href']"
                    />
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
