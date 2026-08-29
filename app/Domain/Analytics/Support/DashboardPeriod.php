<?php

namespace App\Domain\Analytics\Support;

use Carbon\CarbonImmutable;

/**
 * Turns the dashboard's period filter (year / month / custom month range)
 * into a month-aligned [from, to] pair the analytics queries can use, or
 * [null, null] to mean "the default trailing 12 months" — AnalyticsService
 * already knows how to fall back to that window on its own.
 */
class DashboardPeriod
{
    public const DEFAULT_PRESET = 'last_12_months';

    /**
     * @param  array<string, mixed>|null  $filters  the dashboard's ->pageFilters
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    public static function resolve(?array $filters): array
    {
        $f = self::normalize($filters);

        return match ($f['preset']) {
            'year' => self::yearRange($f['year']),
            'month' => self::monthRange($f['year'], $f['month']),
            'range' => self::customRange($f['range_year'], $f['range_from_month'], $f['range_to_month']),
            default => [null, null],
        };
    }

    /**
     * Los filtros, saneados una sola vez.
     *
     * Existe porque `resolve()` y `label()` leían el mismo arreglo por separado, y
     * pudieron describir períodos distintos sin que nada chirriara. Un desplegable de
     * Filament sin selección llega como cadena vacía, y `??` no la sustituye: solo actúa
     * sobre `null` o ausente. De ahí `(int) ''` es **0**, y `Carbon::create(2026, 0, 1)`
     * no falla — devuelve el 1 de diciembre de 2025.
     *
     * Así, un «Hasta» vacío con «Desde: enero» daba un rango de enero al mes cero, que
     * `customRange()` invertía por creerlo al revés, y la pantalla acababa sumando
     * diciembre del año anterior con enero mientras el rótulo decía «Enero – 2026».
     * El número no era el que nadie había pedido.
     *
     * Un mes fuera de 1–12 ya no llega a Carbon: se cae al valor por defecto. Y la
     * inversión del rango se resuelve aquí, para que el rótulo diga el mismo orden que
     * los datos.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{preset: string, year: int, month: int, range_year: int, range_from_month: int, range_to_month: int}
     */
    private static function normalize(?array $filters): array
    {
        $entero = function (string $key, int $fallback, int $min, int $max) use ($filters): int {
            $raw = $filters[$key] ?? null;

            // Blanco es ausencia, no cero. Es la distinción que faltaba.
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                return $fallback;
            }

            $value = (int) $raw;

            return ($value < $min || $value > $max) ? $fallback : $value;
        };

        $anioActual = (int) now()->year;
        $from = $entero('range_from_month', 1, 1, 12);
        $to = $entero('range_to_month', (int) now()->month, 1, 12);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'preset' => is_string($filters['preset'] ?? null) && $filters['preset'] !== ''
                ? $filters['preset']
                : self::DEFAULT_PRESET,
            'year' => $entero('year', $anioActual, 2000, $anioActual + 1),
            'month' => $entero('month', (int) now()->month, 1, 12),
            'range_year' => $entero('range_year', $anioActual, 2000, $anioActual + 1),
            'range_from_month' => $from,
            'range_to_month' => $to,
        ];
    }

    /**
     * El mismo período, pero como ventana concreta y siempre resuelta.
     *
     * `resolve()` devuelve [null, null] para «últimos 12 meses» porque
     * AnalyticsService sabe caer solo a esa ventana. Un widget de foto no: cada
     * uno terminaba inventándose su propio fallback, y el de planta caía al mes
     * en curso mientras el filtro en pantalla decía «últimos 12 meses» — el
     * número no era el que el usuario había pedido, sin avisar.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function snapshotWindow(?array $filters): array
    {
        [$from, $to] = self::resolve($filters);

        if ($from === null || $to === null) {
            $from = CarbonImmutable::now()->startOfMonth()->subMonths(11);
            $to = CarbonImmutable::now()->startOfMonth();
        }

        // resolve() alinea al primer día del mes; una foto necesita el mes completo.
        return [$from->startOfMonth(), $to->endOfMonth()];
    }

    /**
     * El período resuelto, en una línea.
     *
     * Sale de {@see normalize()}, la misma fuente que {@see resolve()}. Leer los filtros
     * por separado fue lo que permitió que el rótulo dijera «Enero – 2026» mientras los
     * datos eran de diciembre a enero: dos lecturas del mismo arreglo pueden discrepar, y
     * discreparon.
     *
     * Un rango de un solo mes se dice como un mes. «Enero – Enero 2026» es una forma rara
     * de escribir «Enero 2026».
     */
    public static function label(?array $filters): string
    {
        $f = self::normalize($filters);
        $months = self::monthNames();

        return match ($f['preset']) {
            'year' => 'año '.$f['year'],
            'month' => $months[$f['month']].' '.$f['year'],
            'range' => $f['range_from_month'] === $f['range_to_month']
                ? $months[$f['range_from_month']].' '.$f['range_year']
                : $months[$f['range_from_month']].' – '.$months[$f['range_to_month']].' '.$f['range_year'],
            default => 'últimos 12 meses',
        };
    }

    /**
     * Same as label(), but for a single-period snapshot figure (e.g. "Costo
     * Mensual") instead of a multi-month trend. The default preset falls back
     * to "este mes" here — matching what ExecutiveDashboardService actually
     * computes when no explicit period is chosen — instead of "últimos 12
     * meses", which would describe a trend widget, not a snapshot one.
     */
    public static function labelForSnapshot(?array $filters): string
    {
        // Por normalize() y no por el filtro crudo: un preset vacío tiene que caer al
        // mismo sitio aquí que en resolve(), o la foto se rotularía distinto de como se
        // calculó.
        return self::normalize($filters)['preset'] === self::DEFAULT_PRESET
            ? 'este mes'
            : self::label($filters);
    }

    /** @return array<int, string> */
    public static function monthOptions(): array
    {
        return self::monthNames();
    }

    /** @return array<int, string> */
    public static function yearOptions(int $span = 5): array
    {
        $currentYear = (int) now()->year;

        return collect(range($currentYear, $currentYear - ($span - 1)))
            ->mapWithKeys(fn (int $year) => [$year => (string) $year])
            ->all();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function yearRange(int $year): array
    {
        $from = CarbonImmutable::create($year, 1, 1)->startOfMonth();
        $to = CarbonImmutable::create($year, 12, 1)->startOfMonth();

        return [$from, $to];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function monthRange(int $year, int $month): array
    {
        $from = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return [$from, $from];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function customRange(int $year, int $fromMonth, int $toMonth): array
    {
        // La inversión ya la resolvió normalize(), y allí a propósito: si se arreglara
        // solo aquí, el rótulo seguiría anunciando el orden que el usuario tecleó
        // mientras los datos usan el corregido.
        $from = CarbonImmutable::create($year, $fromMonth, 1)->startOfMonth();
        $to = CarbonImmutable::create($year, $toMonth, 1)->startOfMonth();

        return [$from, $to];
    }

    /** @return array<int, string> */
    private static function monthNames(): array
    {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
    }
}
