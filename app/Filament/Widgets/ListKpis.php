<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Arr;

/**
 * La fila de tarjetas que va encima de una lista: lo que dice la tabla en tres o cuatro
 * números y, si ayuda, unas barras pequeñas.
 *
 * Cada tarjeta puede llevar el filtro que la explica: tocarla abre la misma lista con ese
 * filtro puesto, y tocarla otra vez lo quita. Los filtros viajan en la dirección
 * (`?filters[...]`, `?tab=`), igual que cuando se ponen a mano, así que el enlace se puede
 * compartir y el botón de volver funciona.
 */
abstract class ListKpis extends Widget
{
    protected string $view = 'filament.widgets.list-kpis';

    protected int|string|array $columnSpan = 'full';

    /** Va arriba de la tabla: se pinta con la página, sin esperar. */
    protected static bool $isLazy = false;

    /**
     * Las tarjetas. `link` son los parámetros de la lista que aplican su filtro.
     *
     * @return list<array{label: string, value: string|int, detail?: ?string, icon?: ?string, color?: ?string, tone?: string, percent?: float|int|null, link?: array<string, mixed>}>
     */
    abstract protected function kpis(): array;

    /**
     * La dirección de la lista con estos parámetros.
     *
     * @param  array<string, mixed>  $parameters
     */
    abstract protected function listUrl(array $parameters = []): string;

    /**
     * Unas barras pequeñas, apiladas por serie.
     *
     * @return array{title: string, labels: list<string>, series: list<array{label: string, color: string, values: list<float|int>}>}|null
     */
    protected function bars(): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $current = Arr::dot(array_filter([
            'filters' => request()->query('filters'),
            'tab' => request()->query('tab'),
        ]));

        $tiles = array_map(function (array $kpi) use ($current): array {
            // Una tarjeta que lleva a otra lista (los repuestos desde los almacenes).
            if (isset($kpi['url'])) {
                return $kpi + ['href' => $kpi['url'], 'active' => false];
            }

            $link = $kpi['link'] ?? null;

            if ($link === null) {
                return $kpi + ['href' => null, 'active' => false];
            }

            $wanted = Arr::dot($link);
            $hasFilter = collect($wanted)->keys()->contains(fn (string $key): bool => str_starts_with($key, 'filters.'));
            $active = $hasFilter && collect($wanted)->every(fn ($value, string $key): bool => array_key_exists($key, $current) && (string) $current[$key] === (string) $value);

            // Activa, se toca para quitar el filtro (y se queda en su pestaña).
            $href = $active
                ? $this->listUrl(array_filter(['tab' => $link['tab'] ?? null]))
                : $this->listUrl($link);

            return $kpi + ['href' => $href, 'active' => $active];
        }, $this->kpis());

        return [
            'tiles' => $tiles,
            'bars' => $this->bars(),
        ];
    }
}
