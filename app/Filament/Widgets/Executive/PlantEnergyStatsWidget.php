<?php

namespace App\Filament\Widgets\Executive;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Domain\Analytics\Support\DashboardPeriod;
use App\Models\Plant;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Los tres números del informe de energía.
 *
 *     KWh TOTAL      = red + planta eléctrica + turbina
 *     KWh/RFF        = KWh TOTAL / fruta procesada
 *     ENERGÍA LIMPIA = turbina / KWh TOTAL
 *
 * KWh/RFF es el que mira gerencia: dice cuánta electricidad cuesta procesar una
 * tonelada, y por eso comparte denominador con la productividad en t/h.
 *
 * «Sin dato» y no cero cuando falta la cifra. Un mes sin capturar no es un mes sin
 * consumo, y cinco meses de 2025 llegaron sin dato de turbina.
 */
class PlantEnergyStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.fronda-stats';

    /** @var array<string, mixed>|null */
    private ?array $energy = null;

    /**
     * La tarjeta grande: lo que cuesta en energía procesar cada tonelada de fruta.
     *
     * @return array<string, mixed>|null
     */
    public function frondaHero(): ?array
    {
        $energy = $this->energy();

        if ($energy === null) {
            return null;
        }

        $number = fn (?float $value, int $decimals = 0): string => $value === null ? 'Sin dato' : number_format($value, $decimals, ',', '.');

        return [
            'label' => 'Energía por tonelada · '.DashboardPeriod::label($this->pageFilters),
            'value' => $energy['kwh_per_ton'],
            'suffix' => ' kWh/t',
            'decimals' => 2,
            'icon' => 'heroicon-o-bolt',
            'badge' => $energy['kwh_per_ton'] === null ? null : ($energy['kwh_per_ton'] <= 25 ? 'Dentro de la meta' : 'Por encima de 25 kWh/t'),
            'empty' => $energy['kwh_per_ton'] === null ? 'Falta el consumo o la fruta del período.' : number_format($energy['processed_tons'], 0, ',', '.').' t procesadas',
            'percent' => $energy['clean_energy_percentage'],
            'progressLabel' => 'Energía limpia (turbina)',
            'stats' => [
                ['label' => 'Consumo total', 'value' => $energy['kwh_total'] === null ? 'Sin dato' : $number($energy['kwh_total']).' kWh', 'detail' => $this->sourceBreakdown($energy)],
                ['label' => 'Energía limpia', 'value' => $energy['clean_energy_percentage'] === null ? 'Sin dato' : $number($energy['clean_energy_percentage'], 1).' %', 'detail' => $energy['kwh_turbine'] === null ? 'Sin lectura de turbina' : $number($energy['kwh_turbine']).' kWh de turbina'],
            ],
            'replaces' => ['KWh por tonelada', 'Consumo total', 'Energía limpia'],
        ];
    }

    protected function getStats(): array
    {
        $energy = $this->energy();

        if ($energy === null) {
            return [Stat::make('Consumo de energía', 'Sin plantas registradas')];
        }

        $period = DashboardPeriod::label($this->pageFilters);

        $kwhPerTon = $energy['kwh_per_ton'];
        $total = $energy['kwh_total'];
        $clean = $energy['clean_energy_percentage'];

        return [
            Stat::make('KWh por tonelada', $kwhPerTon !== null ? number_format($kwhPerTon, 2, ',', '.').' kWh/t' : 'Sin dato')
                ->description($kwhPerTon !== null
                    ? number_format((float) $total, 0, ',', '.').' kWh sobre '
                        .number_format($energy['processed_tons'], 0, ',', '.').' t · '.$period
                    : 'Falta el consumo o la fruta del período')
                // Menos kWh por tonelada es mejor: es lo que cuesta procesar la fruta.
                ->color(match (true) {
                    $kwhPerTon === null => 'gray',
                    $kwhPerTon <= 25 => 'success',
                    $kwhPerTon <= 30 => 'warning',
                    default => 'danger',
                }),

            Stat::make('Consumo total', $total !== null ? number_format($total, 0, ',', '.').' kWh' : 'Sin dato')
                ->description($this->sourceBreakdown($energy).' · '.$period)
                ->color('primary'),

            Stat::make('Energía limpia', $clean !== null ? number_format($clean, 2, ',', '.').'%' : 'Sin dato')
                ->description($clean !== null
                    ? number_format((float) $energy['kwh_turbine'], 0, ',', '.').' kWh de turbina · '.$period
                    : 'Sin lectura de turbina en el período')
                ->color(match (true) {
                    $clean === null => 'gray',
                    $clean >= 80 => 'success',
                    $clean >= 60 => 'warning',
                    default => 'danger',
                }),
        ];
    }

    /** @param array<string, mixed> $energy */
    private function sourceBreakdown(array $energy): string
    {
        $parts = [];

        foreach (['kwh_grid' => 'red', 'kwh_genset' => 'planta', 'kwh_turbine' => 'turbina'] as $key => $label) {
            $parts[] = $energy[$key] === null
                ? "{$label} sin dato"
                : number_format((float) $energy[$key], 0, ',', '.')." {$label}";
        }

        return implode(' · ', $parts);
    }

    /** @return array<string, mixed>|null el consumo del período, una sola vez por pintada */
    private function energy(): ?array
    {
        $plant = $this->selectedPlant();

        if ($plant === null) {
            return null;
        }

        [$from, $to] = DashboardPeriod::snapshotWindow($this->pageFilters);

        return $this->energy ??= app(PlantKpiService::class)->energySummary($plant, $from, $to);
    }

    private function selectedPlant(): ?Plant
    {
        $plantId = $this->pageFilters['plant_id'] ?? null;

        return $plantId !== null
            ? Plant::find($plantId)
            : Plant::orderBy('name')->first();
    }
}
