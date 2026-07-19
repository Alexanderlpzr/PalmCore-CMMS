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
        $preset = $filters['preset'] ?? self::DEFAULT_PRESET;

        return match ($preset) {
            'year' => self::yearRange((int) ($filters['year'] ?? now()->year)),
            'month' => self::monthRange(
                (int) ($filters['year'] ?? now()->year),
                (int) ($filters['month'] ?? now()->month),
            ),
            'range' => self::customRange(
                (int) ($filters['range_year'] ?? now()->year),
                (int) ($filters['range_from_month'] ?? 1),
                (int) ($filters['range_to_month'] ?? now()->month),
            ),
            default => [null, null],
        };
    }

    /** A short human label for the resolved period, for widget subtitles. */
    public static function label(?array $filters): string
    {
        $preset = $filters['preset'] ?? self::DEFAULT_PRESET;
        $months = self::monthNames();

        return match ($preset) {
            'year' => 'año '.((int) ($filters['year'] ?? now()->year)),
            'month' => ($months[(int) ($filters['month'] ?? now()->month)] ?? '').' '.((int) ($filters['year'] ?? now()->year)),
            'range' => ($months[(int) ($filters['range_from_month'] ?? 1)] ?? '').' – '
                .($months[(int) ($filters['range_to_month'] ?? now()->month)] ?? '').' '
                .((int) ($filters['range_year'] ?? now()->year)),
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
        $preset = $filters['preset'] ?? self::DEFAULT_PRESET;

        return $preset === self::DEFAULT_PRESET ? 'este mes' : self::label($filters);
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
        if ($fromMonth > $toMonth) {
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

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
