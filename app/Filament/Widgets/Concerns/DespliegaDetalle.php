<?php

namespace App\Filament\Widgets\Concerns;

/**
 * Una gráfica que, al tocar una barra o una porción, abre debajo su ficha con las cifras
 * de ese punto (2026-11-19). El widget pone `$view = 'filament.widgets.grafica-con-detalle'`
 * y dice aquí qué lleva cada ficha, en el mismo orden que las etiquetas de la gráfica.
 */
trait DespliegaDetalle
{
    /**
     * Una ficha por barra o porción, en el orden de las etiquetas.
     *
     * @return list<array{titulo: string, cifras: list<array{etiqueta: string, valor: string}>, url?: ?string}>
     */
    abstract public function detalles(): array;

    /**
     * @param  array<string, string>  $cifras  etiqueta → valor ya escrito
     * @return array{titulo: string, cifras: list<array{etiqueta: string, valor: string}>, url: ?string}
     */
    protected static function ficha(string $titulo, array $cifras, ?string $url = null): array
    {
        return [
            'titulo' => $titulo,
            'cifras' => array_map(fn (string $etiqueta, string $valor): array => ['etiqueta' => $etiqueta, 'valor' => $valor], array_keys($cifras), array_values($cifras)),
            'url' => $url,
        ];
    }
}
