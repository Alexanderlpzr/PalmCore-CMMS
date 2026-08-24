<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * El año entero en una tabla: parámetros en filas, meses en columnas.
 *
 * Es la forma en que la planta lee sus indicadores desde antes de que existiera el
 * sistema, y por eso la tabla se conserva con las mismas etiquetas de su planilla —
 * RFF/MES, KWh/RFF, KWh TOTAL, ENERGÍA LIMPIA—. Cambiarlas obligaría a traducir mentalmente
 * cada vez.
 *
 * Con dos diferencias deliberadas respecto de la hoja:
 *
 *   - Un mes sin dato muestra «—», no `#DIV/0!` ni un cero. La hoja pone cero en los
 *     meses que aún no han ocurrido, y un cero es una afirmación: dice que la planta no
 *     consumió. El guion no afirma nada, que es la verdad.
 *   - El total de la fila no suma los meses vacíos, así que la columna «Año» es el
 *     acumulado real de lo que se sabe, no de lo que se dejó en blanco.
 */
class PlantEnergyYearTableWidget extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.plant-energy-year-table';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $plant = $this->selectedPlant();
        $year = $this->selectedYear();

        if ($plant === null) {
            return ['year' => $year, 'months' => [], 'rows' => [], 'empty' => true];
        }

        $kpis = PlantMonthlyKpi::withoutGlobalScopes()
            ->where('plant_id', $plant->id)
            ->where('year', $year)
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = mb_strtoupper(
                Carbon::create($year, $m, 1)->translatedFormat('F')
            );
        }

        return [
            'year' => $year,
            'months' => $months,
            'rows' => $this->buildRows($kpis),
            'empty' => false,
        ];
    }

    /**
     * @param  Collection<int, PlantMonthlyKpi>  $kpis
     * @return list<array{label: string, values: array<int, ?string>, total: ?string, strong: bool}>
     */
    private function buildRows($kpis): array
    {
        // Cada fila declara de dónde sale su número y cómo se totaliza el año. Los
        // promedios y los ratios NO se suman: el KWh/RFF del año es el total de kWh
        // partido por el total de fruta, no la media de doce ratios mensuales — que
        // daría más peso a un mes flojo que a uno de plena cosecha.
        $sum = fn (string $field): ?float => $this->sumOf($kpis, $field);

        $tons = $sum('processed_tons');
        $total = $sum('kwh_total');
        $turbine = $sum('kwh_turbine');

        return [
            $this->row('RFF/MES (t)', $kpis, 'processed_tons', 0, $tons),
            $this->row('KWh/RFF', $kpis, 'kwh_per_ton', 2,
                ($total !== null && $tons > 0) ? round($total / $tons, 2) : null, strong: true),
            $this->row('KWh TOTAL', $kpis, 'kwh_total', 0, $total, strong: true),
            $this->row('KWh RED PÚBLICA', $kpis, 'kwh_grid', 0, $sum('kwh_grid')),
            $this->row('KWh PLANTA ELÉCTRICA', $kpis, 'kwh_genset', 0, $sum('kwh_genset')),
            $this->row('KWh TURBINA', $kpis, 'kwh_turbine', 0, $turbine),
            $this->row('ENERGÍA LIMPIA (%)', $kpis, 'clean_energy_percentage', 2,
                ($turbine !== null && $total > 0) ? round($turbine / $total * 100, 2) : null),
        ];
    }

    /**
     * @param  Collection<int, PlantMonthlyKpi>  $kpis
     * @return array{label: string, values: array<int, ?string>, total: ?string, strong: bool}
     */
    private function row(string $label, $kpis, string $field, int $decimals, ?float $total, bool $strong = false): array
    {
        $values = [];

        for ($m = 1; $m <= 12; $m++) {
            $raw = $kpis->get($m)?->{$field};

            $values[$m] = $raw === null ? null : $this->format((float) $raw, $decimals);
        }

        return [
            'label' => $label,
            'values' => $values,
            'total' => $total === null ? null : $this->format($total, $decimals),
            'strong' => $strong,
        ];
    }

    /** @param Collection<int, PlantMonthlyKpi> $kpis */
    private function sumOf($kpis, string $field): ?float
    {
        $known = $kpis->pluck($field)->filter(fn ($v): bool => $v !== null);

        return $known->isEmpty() ? null : round((float) $known->sum(), 2);
    }

    private function format(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', '.');
    }

    private function selectedYear(): int
    {
        $filters = $this->pageFilters;

        if (($filters['preset'] ?? null) === 'range') {
            return (int) ($filters['range_year'] ?? now()->year);
        }

        if (isset($filters['year']) && $filters['year'] !== null) {
            return (int) $filters['year'];
        }

        // «Últimos 12 meses» no tiene año propio: se muestra el del final de la ventana,
        // que es el año en el que la planta está trabajando.
        [, $to] = DashboardPeriod::snapshotWindow($filters);

        return (int) $to->year;
    }

    private function selectedPlant(): ?Plant
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        return $plantId !== null
            ? Plant::find($plantId)
            : Plant::orderBy('name')->first();
    }
}
