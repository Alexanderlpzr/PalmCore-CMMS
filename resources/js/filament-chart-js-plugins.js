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
