<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Support\RawJs;

/**
 * Los valores escritos sobre el gráfico, sin tener que pasar el ratón.
 *
 * Una torta cuyos números solo aparecen al señalar cada porción no se puede leer en una
 * reunión ni imprimir: quien la mira sabe cuál es la grande y nada más. Aquí cada porción
 * lleva sus horas y su porcentaje dibujados encima.
 *
 * Las porciones por debajo del 4 % no escriben nada. Es la misma razón por la que
 * existen: a ese tamaño la etiqueta no cabe dentro del sector, se monta sobre la de al
 * lado y acaba tapando los números que sí importan. Su valor sigue estando en la leyenda
 * y en el tooltip.
 */
trait MuestraLosValores
{
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
                        formatter: (value, ctx) => {
                            const datos = ctx.chart.data.datasets[0].data;
                            const total = datos.reduce((suma, n) => suma + (Number(n) || 0), 0);

                            if (!total || !value) {
                                return null;
                            }

                            const porcentaje = (value / total) * 100;

                            if (porcentaje < 4) {
                                return null;
                            }

                            const horas = Number(value).toLocaleString('es-CO', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 1,
                            });

                            return horas + ' h\n' + porcentaje.toFixed(1) + '%';
                        },
                        textAlign: 'center',
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: { boxWidth: 12, padding: 10 },
                    },
                },
            }
        JS);
    }
}
