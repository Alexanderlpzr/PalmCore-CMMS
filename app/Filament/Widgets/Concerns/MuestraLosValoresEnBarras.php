<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Support\RawJs;

/**
 * Lo mismo que {@see MuestraLosValores}, pero para barras y columnas: el número va fuera
 * de la barra, no encima.
 *
 * En una torta la etiqueta cabe dentro del sector; en una barra corta no cabe nada, y
 * escribirla dentro la haría ilegible justo en las barras pequeñas — que en un Pareto son
 * la mayoría. Puesta al final de la barra se lee siempre, y sin porcentaje: una barra
 * responde «cuántas horas en este equipo», no «qué parte del total».
 */
trait MuestraLosValoresEnBarras
{
    protected function getOptions(): RawJs
    {
        $ejeHorizontal = $this->barrasHorizontales() ? "indexAxis: 'y'," : '';

        return RawJs::make(<<<JS
            {
                {$ejeHorizontal}
                layout: { padding: { right: 28, top: 18 } },
                plugins: {
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        offset: 2,
                        color: '#475569',
                        font: { weight: 'bold', size: 10 },
                        formatter: (value) => {
                            if (!value) {
                                return null;
                            }

                            return Number(value).toLocaleString('es-CO', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 1,
                            }) + ' h';
                        },
                    },
                },
            }
        JS);
    }

    /** Barras acostadas cuando las etiquetas son nombres largos, como los de equipo. */
    protected function barrasHorizontales(): bool
    {
        return false;
    }
}
