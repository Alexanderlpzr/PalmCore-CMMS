<?php

namespace App\Filament\Filters;

use Carbon\CarbonImmutable;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * La barra «Periodo · Año · Mes» de talento humano ({@see PayrollPeriodFilter}) para las
 * tablas de las demás áreas: mantenimiento, almacén, SST y el resto de listas con fecha.
 *
 * Pedida el 2026-11-08 para todas las tablas: elegir un mes, un año o un rango y descargar
 * el Excel de eso. Fuera de la nómina no hay cortes del 27 al 26, así que no se ofrecen.
 *
 * Abre en «Todo» a propósito: estas listas mostraban todo antes de tener la barra, y abrir
 * en el mes en curso escondería de golpe lo pendiente de meses anteriores. Ofrece además
 * «Últimos 7 días», el atajo que paros y órdenes de trabajo tenían en su filtro anterior.
 */
final class PeriodFilter
{
    /**
     * @param  string  $column  la columna de fecha que filtra
     * @param  string  $label  cómo se llama esa fecha, para el indicador y el título del Excel
     *                         —una tabla puede mostrar varias fechas y hay que decir cuál—
     * @param  ?string  $timezone  para las columnas guardadas en UTC que se piensan en hora
     *                             local: el día se corta a medianoche de esa zona
     */
    public static function make(string $column, string $label = 'Fecha', string $name = 'periodo', ?string $timezone = null): Filter
    {
        return PayrollPeriodFilter::make(null, $name, allowAll: true, lastDays: true)
            ->query(function (Builder $query, array $data) use ($column, $timezone): Builder {
                [$desde, $hasta] = PayrollPeriodFilter::window($data);

                if ($timezone !== null) {
                    return $query
                        ->when($desde, fn (Builder $q, string $d): Builder => $q->where($column, '>=', CarbonImmutable::parse($d, $timezone)->startOfDay()->utc()))
                        ->when($hasta, fn (Builder $q, string $h): Builder => $q->where($column, '<=', CarbonImmutable::parse($h, $timezone)->endOfDay()->utc()));
                }

                return $query
                    ->when($desde, fn (Builder $q, string $d): Builder => $q->whereDate($column, '>=', $d))
                    ->when($hasta, fn (Builder $q, string $h): Builder => $q->whereDate($column, '<=', $h));
            })
            ->indicateUsing(fn (array $data): array => self::indicator($label, $data));
    }

    /**
     * Para las tablas que guardan el período en dos números y no en una fecha, como el
     * presupuesto de mantenimiento: entra el mes que caiga dentro de lo elegido.
     */
    public static function yearMonth(string $yearColumn, string $monthColumn, string $label = 'Período', string $name = 'periodo'): Filter
    {
        $ordinal = "({$yearColumn} * 100 + {$monthColumn})";
        $toOrdinal = fn (string $date): int => (int) CarbonImmutable::parse($date)->format('Ym');

        return PayrollPeriodFilter::make(null, $name, allowAll: true)
            ->query(function (Builder $query, array $data) use ($ordinal, $toOrdinal): Builder {
                [$desde, $hasta] = PayrollPeriodFilter::window($data);

                return $query
                    ->when($desde, fn (Builder $q, string $d): Builder => $q->whereRaw("{$ordinal} >= ?", [$toOrdinal($d)]))
                    ->when($hasta, fn (Builder $q, string $h): Builder => $q->whereRaw("{$ordinal} <= ?", [$toOrdinal($h)]));
            })
            ->indicateUsing(fn (array $data): array => self::indicator($label, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function indicator(string $label, array $data): array
    {
        $period = PayrollPeriodFilter::label($data);

        return $period === null ? [] : ["{$label}: ".mb_strtolower($period, 'UTF-8')];
    }
}
