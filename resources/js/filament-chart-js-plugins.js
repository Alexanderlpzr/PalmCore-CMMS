import { ArcElement, BarElement, PointElement } from 'chart.js'
import ChartDataLabels from 'chartjs-plugin-datalabels'

/*
 * Los números encima de la gráfica, sin pasar el ratón.
 *
 * Un gráfico de torta cuyos valores solo aparecen al señalar cada porción no sirve en
 * una reunión: nadie va a pasar el ratón por siete porciones mientras habla, y la
 * lectura acaba siendo «esa azul es la grande». Con las etiquetas dibujadas, la gráfica
 * se lee de un vistazo y se puede imprimir o proyectar.
 *
 * Se registra en el arreglo que Filament ya mira al montar cada gráfica, para no pisar
 * los complementos que registren otros.
 */
window.filamentChartJsPlugins ??= []
window.filamentChartJsPlugins.push(ChartDataLabels)

/*
 * El complemento trae su propia copia de Chart.js y Filament dibuja con otra, así que
 * `elemento instanceof ArcElement` (o BarElement, o PointElement) daba falso:
 *  - en las tortas buscaba un eje que no tienen y la gráfica entera se caía;
 *  - en las barras caía en su ubicación de respaldo y pintaba la cifra abajo, junto a la
 *    base, en vez de encima de la barra (2026-11-19).
 * Se compara por el `id` de la clase, que es el mismo en las dos copias.
 */
for (const Element of [ArcElement, BarElement, PointElement]) {
    Object.defineProperty(Element, Symbol.hasInstance, {
        value: (element) => element?.constructor?.id === Element.id,
    })
}

/*
 * El color de las cifras según el tema: casi negro en claro, casi blanco en oscuro. Un
 * gris fijo se perdía sobre el fondo oscuro. Las gráficas lo usan con
 * `color: window.frondaChartLabelColor`.
 */
window.frondaChartLabelColor = () =>
    document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#0f172a'
