<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Una gráfica sin datos dice que no hay datos, en vez de pintar ejes vacíos.
 *
 * Unos ejes en blanco se leen como «algo se dañó» o, peor, como «todo en cero». Con el
 * mismo estado vacío de los tableros de Talento Humano, la tarjeta conserva su título y
 * explica qué falta. En cuanto el período tiene datos, vuelve la gráfica.
 */
trait DiceCuandoNoHayDatos
{
    public function render(): View
    {
        if ($this->noHayDatos()) {
            return view('filament.widgets.chart-sin-datos', [
                'heading' => $this->getHeading(),
                'description' => $this->getDescription(),
                'mensaje' => $this->mensajeSinDatos(),
            ]);
        }

        return parent::render();
    }

    /** Lo que dice la tarjeta cuando está vacía. Cada gráfica puede decir qué le falta. */
    protected function mensajeSinDatos(): string
    {
        return 'Todavía no hay datos en este período.';
    }

    /** ¿Todos los valores de todas las series son cero o faltan? */
    protected function noHayDatos(): bool
    {
        foreach ($this->getCachedData()['datasets'] ?? [] as $dataset) {
            foreach ($dataset['data'] ?? [] as $value) {
                $number = is_array($value) ? ($value['y'] ?? $value['x'] ?? 0) : $value;

                if (is_numeric($number) && (float) $number !== 0.0) {
                    return false;
                }
            }
        }

        return true;
    }
}
