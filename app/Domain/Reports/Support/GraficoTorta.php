<?php

namespace App\Domain\Reports\Support;

/**
 * Una torta para los informes en PDF.
 *
 * Se dibuja en SVG y se entrega como `data:` URI para incrustar en un `<img>`. Ese rodeo
 * no es una preferencia: **DomPDF ignora un `<svg>` escrito en el HTML**. Se comprobó
 * generando un PDF con un sector en línea y leyendo su flujo de contenido — cero curvas,
 * cero rellenos, el elemento desaparece sin dar error. Como imagen sí lo dibuja.
 *
 * Va en PHP y no en una vista porque es trigonometría y agrupación, no maquetación.
 */
class GraficoTorta
{
    /**
     * Cuántas porciones antes de agrupar el resto en «Otros».
     *
     * Una torta de nueve porciones finas no se lee: los sectores pequeños quedan sin sitio
     * para su etiqueta y el ojo no distingue cuál es mayor. Seis es donde deja de leerse.
     * No se pierde nada — la tabla que acompaña a cada gráfico sigue listando todas las
     * filas, y ahí es donde se va a buscar el detalle.
     */
    public const MAXIMO_PORCIONES = 6;

    /**
     * La paleta, en el orden en que se reparte.
     *
     * Es la de los informes: el verde de la casa primero, y después colores que se
     * distinguen entre sí incluso impresos en escala de grises, porque la mitad de estos
     * documentos acaban en una fotocopia.
     *
     * @var list<string>
     */
    public const COLORES = ['#059669', '#d97706', '#dc2626', '#0891b2', '#7c3aed', '#65a30d', '#94a3b8'];

    /**
     * Prepara las porciones: descarta los ceros, ordena y agrupa la cola.
     *
     * Un valor en cero **no dibuja porción**. Un sector de ángulo cero no se ve, pero su
     * etiqueta sí aparecería en la leyenda afirmando que participa de algo, y no participa.
     * Es el mismo error que la astilla de barra del 1,5 % que hubo que corregir.
     *
     * @param  array<string, float>  $valores  etiqueta => valor
     * @return list<array{label: string, value: float, percentage: float, color: string}>
     */
    public static function porciones(array $valores): array
    {
        $positivos = array_filter($valores, fn (float $v): bool => $v > 0);

        if ($positivos === []) {
            return [];
        }

        arsort($positivos);

        if (count($positivos) > self::MAXIMO_PORCIONES) {
            $principales = array_slice($positivos, 0, self::MAXIMO_PORCIONES - 1, preserve_keys: true);
            $cola = array_slice($positivos, self::MAXIMO_PORCIONES - 1, preserve_keys: true);

            $principales['Otros ('.count($cola).')'] = array_sum($cola);
            $positivos = $principales;
        }

        $total = array_sum($positivos);
        $porciones = [];
        $i = 0;

        foreach ($positivos as $etiqueta => $valor) {
            $porciones[] = [
                'label' => (string) $etiqueta,
                'value' => round($valor, 2),
                'percentage' => round($valor / $total * 100, 1),
                'color' => self::COLORES[$i % count(self::COLORES)],
            ];
            $i++;
        }

        return $porciones;
    }

    /**
     * La torta como `data:` URI, lista para el `src` de un `<img>`.
     *
     * Una sola porción se dibuja como círculo completo y no como sector: un arco de 360°
     * tiene el mismo punto de inicio y de fin, y el motor lo resuelve como un arco de cero
     * grados — la torta sale vacía.
     *
     * @param  list<array{label: string, value: float, percentage: float, color: string}>  $porciones
     */
    public static function svg(array $porciones, int $tamano = 150): string
    {
        $r = $tamano / 2 - 2;
        $c = $tamano / 2;

        $partes = '';

        if (count($porciones) === 1) {
            $partes = sprintf(
                '<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s"/>',
                $c, $c, $r, $porciones[0]['color'],
            );
        } else {
            // Se arranca en las doce en punto: es donde el ojo empieza a leer una torta.
            $angulo = -90.0;

            foreach ($porciones as $porcion) {
                $fin = $angulo + $porcion['percentage'] * 3.6;
                $partes .= self::sector($c, $c, $r, $angulo, $fin, $porcion['color']);
                $angulo = $fin;
            }
        }

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s</svg>',
            $tamano, $tamano, $tamano, $tamano, $partes,
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Un sector: del centro al borde, el arco, y de vuelta al centro. */
    private static function sector(float $cx, float $cy, float $r, float $desde, float $hasta, string $color): string
    {
        $x1 = $cx + $r * cos(deg2rad($desde));
        $y1 = $cy + $r * sin(deg2rad($desde));
        $x2 = $cx + $r * cos(deg2rad($hasta));
        $y2 = $cy + $r * sin(deg2rad($hasta));

        // El indicador de arco mayor: sin él, todo sector de más de media vuelta se dibuja
        // por el lado corto y la torta sale con un mordisco.
        $mayor = ($hasta - $desde) > 180 ? 1 : 0;

        return sprintf(
            '<path d="M %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f Z" fill="%s"/>',
            $cx, $cy, $x1, $y1, $r, $r, $mayor, $x2, $y2, $color,
        );
    }
}
