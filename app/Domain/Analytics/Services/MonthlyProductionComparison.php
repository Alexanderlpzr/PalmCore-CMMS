<?php

namespace App\Domain\Analytics\Services;

use App\Models\PlantMonthlyKpi;
use Carbon\CarbonImmutable;

/**
 * La producción de cada mes de un año al lado de la del mismo mes del año anterior: las
 * toneladas procesadas, las horas efectivas y lo que rindió cada hora.
 *
 * Sale del cierre mensual de la planta (plant_monthly_kpis), el mismo que usan Producción
 * y Consumo de energía; el mes en curso se rehace al guardar los días de producción. Un
 * mes sin cierre es «sin dato», no cero: aún no ha pasado o nadie lo cargó.
 */
class MonthlyProductionComparison
{
    private const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    /**
     * @return array{
     *     year: int,
     *     previousYear: int,
     *     rows: list<array{month: int, label: string, tons: ?float, previousTons: ?float, change: ?float, effectiveHours: ?float, tonsPerHour: ?float}>,
     *     total: array{tons: float, previousTons: float, change: ?float, effectiveHours: float, tonsPerHour: ?float, months: int},
     *     best: ?array{label: string, tons: float}
     * }
     */
    public function forYear(string $tenantId, string $plantId, int $year, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        $kpis = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('plant_id', $plantId)
            ->whereIn('year', [$year, $year - 1])
            ->get(['year', 'month', 'processed_tons', 'effective_hours'])
            ->keyBy(fn (PlantMonthlyKpi $kpi): string => $kpi->year.'-'.$kpi->month);

        $tons = fn (int $y, int $m): ?float => self::positive($kpis->get("{$y}-{$m}")?->processed_tons);

        $rows = [];
        $total = ['tons' => 0.0, 'previousTons' => 0.0, 'effectiveHours' => 0.0, 'months' => 0];

        foreach (range(1, 12) as $month) {
            // Un mes que no ha empezado no tiene dato, aunque haya un cierre en cero.
            $started = CarbonImmutable::create($year, $month, 1)->lte($today);
            $current = $started ? $tons($year, $month) : null;
            $previous = $tons($year - 1, $month);
            $hours = $started ? self::positive($kpis->get("{$year}-{$month}")?->effective_hours) : null;

            $rows[] = [
                'month' => $month,
                'label' => self::MONTHS[$month - 1],
                'tons' => $current,
                'previousTons' => $previous,
                'change' => self::change($current, $previous),
                'effectiveHours' => $hours,
                'tonsPerHour' => $current !== null && $hours ? round($current / $hours, 2) : null,
            ];

            // El total compara lo mismo con lo mismo: solo los meses que tienen dato este
            // año, contra esos mismos meses del anterior.
            if ($current !== null) {
                $total['tons'] += $current;
                $total['previousTons'] += $previous ?? 0.0;
                $total['effectiveHours'] += $hours ?? 0.0;
                $total['months']++;
            }
        }

        $best = collect($rows)->filter(fn (array $row): bool => $row['tons'] !== null)->sortByDesc('tons')->first();

        return [
            'year' => $year,
            'previousYear' => $year - 1,
            'rows' => $rows,
            'total' => [
                'tons' => round($total['tons'], 2),
                'previousTons' => round($total['previousTons'], 2),
                'change' => self::change($total['tons'] ?: null, $total['previousTons'] ?: null),
                'effectiveHours' => round($total['effectiveHours'], 1),
                'tonsPerHour' => $total['effectiveHours'] > 0 ? round($total['tons'] / $total['effectiveHours'], 2) : null,
                'months' => $total['months'],
            ],
            'best' => $best ? ['label' => $best['label'], 'tons' => $best['tons']] : null,
        ];
    }

    /** Cuánto subió o bajó, en porcentaje, contra el año anterior. */
    private static function change(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || $previous <= 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    private static function positive(?float $value): ?float
    {
        return $value !== null && $value > 0 ? round($value, 2) : null;
    }
}
