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
                        // 'auto' esconde la etiqueta que se montaría sobre otra. Sin esto,
                        // en la torta de Tipo II «5,9 h 11.6%» se superponía con
                        // «2,5 h 4.8%» y las dos quedaban ilegibles — comprobado en
                        // pantalla con los datos de septiembre.
                        display: 'auto',
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

                            // Solo el porcentaje, en una línea: es lo que una torta
                            // responde bien —qué parte del total se lleva cada causa— y las
                            // horas exactas ya están en la leyenda, completas y sin
                            // taparse. Con dos líneas la etiqueta ocupaba el doble y se
                            // pisaba con la de al lado en cuanto había más de cuatro
                            // porciones.
                            return porcentaje.toLocaleString('es-CO', {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1,
                            }) + ' %';
                        },
                        textAlign: 'center',
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            // Las horas al lado del nombre. La etiqueta dibujada sobre el
                            // sector se pierde en las porciones finas —y en las de menos
                            // del 4 % ni se escribe—, así que la leyenda es el único sitio
                            // donde el valor de TODAS las porciones se puede leer.
                            generateLabels: (chart) => {
                                const conjunto = chart.data.datasets[0] ?? { data: [] };
                                const colores = conjunto.backgroundColor ?? [];

                                return (chart.data.labels ?? []).map((etiqueta, i) => {
                                    const valor = Number(conjunto.data[i]) || 0;
                                    const horas = valor.toLocaleString('es-CO', {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 1,
                                    });

                                    return {
                                        text: etiqueta + ' — ' + horas + ' h',
                                        fillStyle: Array.isArray(colores) ? colores[i] : colores,
                                        strokeStyle: Array.isArray(colores) ? colores[i] : colores,
                                        lineWidth: 0,
                                        hidden: !chart.getDataVisibility(i),
                                        index: i,
                                    };
                                });
                            },
                        },
                    },
                },
            }
        JS);
    }
}
