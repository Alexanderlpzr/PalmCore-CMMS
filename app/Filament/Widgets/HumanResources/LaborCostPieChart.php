<?php

namespace App\Filament\Widgets\HumanResources;

use App\Filament\Widgets\Concerns\DiceCuandoNoHayDatos;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

/**
 * Una torta del tablero «Costo de personal». No consulta nada: la página arma el informe
 * una sola vez y le pasa las porciones, así las cuatro tortas cuadran entre sí y con las
 * tablas de abajo.
 */
class LaborCostPieChart extends ChartWidget
{
    use DiceCuandoNoHayDatos;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public string $chartHeading = '';

    #[Reactive]
    public ?string $chartDescription = null;

    /** @var list<array{label: string, value: float, color: string}> */
    #[Reactive]
    public array $slices = [];

    public function getHeading(): string
    {
        return $this->chartHeading;
    }

    public function getDescription(): ?string
    {
        return $this->chartDescription;
    }

    protected function getData(): array
    {
        $slices = array_values(array_filter($this->slices, fn (array $slice): bool => $slice['value'] > 0));

        return [
            'datasets' => [[
                'label' => $this->chartHeading,
                'data' => array_map(fn (array $slice): float => round($slice['value']), $slices),
                'backgroundColor' => array_column($slices, 'color'),
                'borderWidth' => 1,
            ]],
            'labels' => array_column($slices, 'label'),
        ];
    }

    /**
     * El porcentaje sobre cada porción y los pesos en la leyenda, como en las tortas de
     * paros (`MuestraLosValores`), pero en pesos.
     */
    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 11 },
                        textStrokeColor: 'rgba(15, 23, 42, 0.55)',
                        textStrokeWidth: 3,
                        display: 'auto',
                        formatter: (value, ctx) => {
                            const datos = ctx.chart.data.datasets[0].data;
                            const total = datos.reduce((suma, n) => suma + (Number(n) || 0), 0);

                            if (!total || !value || (value / total) * 100 < 4) {
                                return null;
                            }

                            return ((value / total) * 100).toLocaleString('es-CO', {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1,
                            }) + ' %';
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ' $ ' + Math.round(Number(ctx.parsed) || 0).toLocaleString('es-CO'),
                        },
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            generateLabels: (chart) => {
                                const conjunto = chart.data.datasets[0] ?? { data: [] };
                                const colores = conjunto.backgroundColor ?? [];
                                // El gris por omisión de Chart.js no se lee sobre el fondo oscuro.
                                const texto = document.documentElement.classList.contains('dark') ? '#e5e7eb' : '#374151';

                                return (chart.data.labels ?? []).map((etiqueta, i) => ({
                                    text: etiqueta + ' — $ ' + Math.round(Number(conjunto.data[i]) || 0).toLocaleString('es-CO'),
                                    fillStyle: Array.isArray(colores) ? colores[i] : colores,
                                    strokeStyle: Array.isArray(colores) ? colores[i] : colores,
                                    fontColor: texto,
                                    lineWidth: 0,
                                    hidden: !chart.getDataVisibility(i),
                                    index: i,
                                }));
                            },
                        },
                    },
                },
            }
        JS);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
