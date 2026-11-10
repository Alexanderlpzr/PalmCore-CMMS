<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\MonthlyProductionComparison;
use App\Filament\Widgets\Concerns\DiceCuandoNoHayDatos;
use App\Filament\Widgets\Costs\Concerns\ResolvesCostReportFilters;
use App\Support\FrondaPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * La producción de cada mes del año, en barras al lado del mismo mes del año anterior, y
 * debajo la tabla con las cifras: toneladas, variación, horas efectivas y toneladas por
 * hora. Lee la planta y el año del filtro del Dashboard.
 */
class MonthlyProductionWidget extends ChartWidget
{
    use DiceCuandoNoHayDatos;
    use InteractsWithPageFilters;
    use ResolvesCostReportFilters;

    protected string $view = 'filament.widgets.produccion-por-mes';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    /** @var array<string, mixed>|null */
    private ?array $comparison = null;

    public function getHeading(): string
    {
        return 'Producción por mes · '.$this->resolvePeriod()[0];
    }

    public function getDescription(): string
    {
        [$year] = $this->resolvePeriod();

        return 'Toneladas de fruta procesadas cada mes, al lado del mismo mes de '.($year - 1).'.';
    }

    /** @return array<string, mixed> */
    public function comparison(): array
    {
        if ($this->comparison !== null) {
            return $this->comparison;
        }

        $plantId = $this->resolvePlantId();

        return $this->comparison = $plantId === null
            ? ['rows' => [], 'total' => null, 'year' => $this->resolvePeriod()[0], 'previousYear' => $this->resolvePeriod()[0] - 1, 'best' => null]
            : app(MonthlyProductionComparison::class)->forYear($this->tenantId(), $plantId, $this->resolvePeriod()[0]);
    }

    protected function getData(): array
    {
        $comparison = $this->comparison();

        if ($comparison['rows'] === []) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = collect($comparison['rows']);

        return [
            'datasets' => [
                [
                    'label' => (string) $comparison['year'],
                    'data' => $rows->pluck('tons')->all(),
                    'backgroundColor' => FrondaPalette::LogoGreen,
                    'borderRadius' => 4,
                ],
                [
                    'label' => (string) $comparison['previousYear'],
                    'data' => $rows->pluck('previousTons')->all(),
                    'backgroundColor' => 'rgba(148, 163, 184, 0.55)',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    protected function mensajeSinDatos(): string
    {
        return 'Todavía no hay meses cerrados con toneladas procesadas para esta planta.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                layout: { padding: { top: 18 } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (value) => Number(value).toLocaleString('es-CO') + ' t' },
                    },
                },
                plugins: {
                    legend: { display: true, position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + (context.parsed.y === null
                                ? 'sin dato'
                                : Number(context.parsed.y).toLocaleString('es-CO', { maximumFractionDigits: 1 }) + ' t'),
                        },
                    },
                    // Solo la cifra del año que se mira, encima de su barra: con las dos
                    // series rotuladas no se lee ninguna.
                    datalabels: {
                        display: (context) => context.datasetIndex === 0 && !!context.dataset.data[context.dataIndex],
                        anchor: 'end',
                        align: 'end',
                        offset: 2,
                        color: '#475569',
                        font: { weight: 'bold', size: 10 },
                        formatter: (value) => Number(value).toLocaleString('es-CO', { maximumFractionDigits: 0 }),
                    },
                },
            }
        JS);
    }
}
