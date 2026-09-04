<?php

namespace App\Filament\Filters;

use App\Filament\Concerns\HasPeriodFilterForm;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Filtrar una tabla por rango de fechas, con atajos y los dos extremos opcionales.
 *
 * Las páginas de Indicadores tienen su selector de período desde hace tiempo
 * ({@see HasPeriodFilterForm}), pero ese trabaja en meses y vive
 * en el formulario de filtros de un Dashboard, así que ninguna tabla del producto podía
 * usarlo. El resultado era que en Paros —donde la pregunta habitual es «qué pasó la
 * semana pasada»— había que ordenar por fecha y desplazarse.
 *
 * Los dos extremos son opcionales a propósito. «Desde el 1 de agosto» sin fecha final es
 * la consulta más frecuente al revisar lo que lleva el mes, y obligar a poner las dos
 * fechas convierte una pregunta en dos.
 *
 * Se compara con `whereDate` y no con el timestamp completo: quien escribe «hasta el 30»
 * quiere incluir el día 30 entero, no cortarlo a las 00:00. Es el error que hace que
 * falte justo el último día.
 */
class DateRangeFilter
{
    /**
     * Los atajos, y qué ventana escribe cada uno.
     *
     * `startOfMonth()` va **antes** de restar, siempre. Restarle un mes a un 31 de agosto
     * cae en el 31 de junio, que no existe, y Carbon lo desborda al 1 de julio: «Mes
     * pasado» acabaría trayendo julio y agosto mezclados. Solo falla un día de cada
     * treinta y uno, que es justo lo que hace que nadie lo note.
     *
     * @return array<string, array{label: string, desde: Carbon, hasta: Carbon}>
     */
    private static function atajos(): array
    {
        $hoy = Carbon::today();
        $mes = $hoy->copy()->startOfMonth();

        return [
            'este_mes' => [
                'label' => 'Este mes',
                'desde' => $mes->copy(),
                'hasta' => $mes->copy()->endOfMonth(),
            ],
            'mes_pasado' => [
                'label' => 'Mes pasado',
                'desde' => $mes->copy()->subMonth(),
                'hasta' => $mes->copy()->subMonth()->endOfMonth(),
            ],
            'ultimos_7' => [
                'label' => 'Últimos 7 días',
                'desde' => $hoy->copy()->subDays(6),
                'hasta' => $hoy->copy(),
            ],
            'este_anio' => [
                'label' => 'Este año',
                'desde' => $hoy->copy()->startOfYear(),
                'hasta' => $hoy->copy()->endOfYear(),
            ],
        ];
    }

    /**
     * La ventana que de verdad se va a consultar, resuelta en un solo sitio.
     *
     * Las fechas mandan cuando están puestas; si no, se resuelve el atajo. Que esto viva
     * aquí y no en el `afterStateUpdated` del botón no es un detalle: mientras el
     * significado del atajo estuvo en un callback de la interfaz, el filtro no se podía
     * ejercer desde un test ni desde una URL — el estado llegaba con `atajo` puesto y sin
     * fechas, y no filtraba nada en silencio.
     *
     * El botón sigue escribiendo las fechas al pulsarlo, pero eso es para que el usuario
     * las vea y las pueda corregir, no para decidir qué se muestra.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{label: string, desde: Carbon, hasta: Carbon}>  $atajos
     * @return array{0: ?string, 1: ?string}
     */
    private static function ventana(array $data, array $atajos): array
    {
        $atajo = $atajos[$data['atajo'] ?? ''] ?? ['desde' => null, 'hasta' => null];

        return [
            ($data['desde'] ?? null) ?: $atajo['desde']?->toDateString(),
            ($data['hasta'] ?? null) ?: $atajo['hasta']?->toDateString(),
        ];
    }

    public static function make(
        string $column = 'created_at',
        string $label = 'Fecha',
        string $name = 'rango_de_fechas',
    ): Filter {
        $atajos = self::atajos();

        return Filter::make($name)
            ->schema([
                // Botones y no un desplegable: un desplegable son dos clics, uno solo para
                // abrirlo, y todo esto existe para ahorrar clics.
                //
                // Pulsarlo escribe las fechas para que se vean y se puedan corregir. Quién
                // manda cuando las dos cosas están puestas lo decide {@see ventana()}, en
                // un solo sitio: guardar el período en dos lugares que pueden discrepar es
                // el fallo que ya tuvo el selector de los Indicadores, donde el rótulo decía
                // «Enero» y los datos traían diciembre y enero.
                ToggleButtons::make('atajo')
                    // Con etiqueta y no oculta: sin ella los botones se pegan arriba de su
                    // celda mientras las dos fechas bajan por las suyas, y la fila queda
                    // desalineada. De paso dice qué son.
                    ->label('Período')
                    ->inline()
                    ->grouped()
                    ->options(array_map(fn (array $a): string => $a['label'], $atajos))
                    ->columnSpan(2)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) use ($atajos): void {
                        if ($state === null) {
                            return;
                        }

                        $set('desde', $atajos[$state]['desde']->toDateString());
                        $set('hasta', $atajos[$state]['hasta']->toDateString());
                    }),

                DatePicker::make('desde')
                    ->label("{$label} desde")
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(fn (Get $get) => $get('hasta') ?: null)
                    ->live()
                    // Tocar una fecha a mano apaga el atajo: si no, quedaría un botón
                    // resaltado diciendo «Este mes» mientras las fechas dicen otra cosa.
                    ->afterStateUpdated(fn (Set $set) => $set('atajo', null)),

                DatePicker::make('hasta')
                    ->label("{$label} hasta")
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(fn (Get $get) => $get('desde') ?: null)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('atajo', null)),
            ])
            // El filtro ocupa la fila entera de la rejilla de filtros, y dentro se reparte
            // en cuatro: los botones a la izquierda ocupando la mitad, y las dos fechas a
            // la derecha. En una sola línea y sin huecos.
            //
            // Encajado en una sola columna —un tercio del ancho— los botones se salían de
            // su celda y se montaban encima del filtro de al lado. Un control con varias
            // partes no cabe en el hueco pensado para un desplegable.
            ->columnSpanFull()
            ->columns(4)
            ->query(function (Builder $query, array $data) use ($column, $atajos): Builder {
                [$desde, $hasta] = self::ventana($data, $atajos);

                return $query
                    ->when($desde, fn (Builder $q, string $d): Builder => $q->whereDate($column, '>=', $d))
                    ->when($hasta, fn (Builder $q, string $h): Builder => $q->whereDate($column, '<=', $h));
            })
            // Sin esto, un filtro de fecha aplicado no se ve en ninguna parte: la tabla
            // muestra menos filas y nada dice por qué. Es el fallo que hace pensar que
            // faltan datos, y pesa más ahora que el filtro se guarda entre visitas.
            ->indicateUsing(function (array $data) use ($label, $atajos): array {
                [$desde, $hasta] = self::ventana($data, $atajos);

                // El atajo se dice por su nombre cuando es él quien manda; en cuanto las
                // fechas dejan de coincidir con su ventana, se dicen las fechas.
                $atajo = $atajos[$data['atajo'] ?? ''] ?? null;

                if ($atajo
                    && $desde === $atajo['desde']->toDateString()
                    && $hasta === $atajo['hasta']->toDateString()) {
                    return ["{$label}: ".mb_strtolower($atajo['label'])];
                }

                $indicators = [];

                if ($desde) {
                    $indicators[] = "{$label} desde ".Carbon::parse($desde)->format('d/m/Y');
                }

                if ($hasta) {
                    $indicators[] = "{$label} hasta ".Carbon::parse($hasta)->format('d/m/Y');
                }

                return $indicators;
            });
    }
}
