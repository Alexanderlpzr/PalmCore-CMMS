<?php

namespace App\Filament\Filters;

use App\Filament\Concerns\HasPeriodFilterForm;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Filtrar una tabla por rango de fechas, con los dos extremos opcionales.
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
    public static function make(
        string $column = 'created_at',
        string $label = 'Fecha',
        string $name = 'rango_de_fechas',
    ): Filter {
        return Filter::make($name)
            ->schema([
                DatePicker::make('desde')
                    ->label("{$label} desde")
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(fn (callable $get) => $get('hasta') ?: null),
                DatePicker::make('hasta')
                    ->label("{$label} hasta")
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(fn (callable $get) => $get('desde') ?: null),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when(
                    $data['desde'] ?? null,
                    fn (Builder $q, string $desde): Builder => $q->whereDate($column, '>=', $desde),
                )
                ->when(
                    $data['hasta'] ?? null,
                    fn (Builder $q, string $hasta): Builder => $q->whereDate($column, '<=', $hasta),
                ))
            // Sin esto, un filtro de fecha aplicado no se ve en ninguna parte: la tabla
            // muestra menos filas y nada dice por qué. Es el fallo que hace pensar que
            // faltan datos.
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];

                if ($data['desde'] ?? null) {
                    $indicators[] = "{$label} desde ".Carbon::parse($data['desde'])->format('d/m/Y');
                }

                if ($data['hasta'] ?? null) {
                    $indicators[] = "{$label} hasta ".Carbon::parse($data['hasta'])->format('d/m/Y');
                }

                return $indicators;
            });
    }
}
