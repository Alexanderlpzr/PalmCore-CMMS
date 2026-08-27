<?php

namespace App\Domain\Analytics\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\EnergyMeterReading;
use App\Models\Plant;
use App\Models\PlantMonthlyKpi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Corregir a mano el total de un mes de la planilla de energía.
 *
 * Existe porque las cifras se equivocan y hasta ahora arreglarlas exigía entrar por
 * consola. No es hipotético: la hoja original traía la turbina de agosto inflada en
 * 3.706 kWh por una fórmula que restaba la fila equivocada, y la producción del mismo mes
 * entró entera en kilogramos.
 *
 * Solo se escriben cuatro cifras. `kwh_total`, `kwh_per_ton` y `clean_energy_percentage`
 * son columnas generadas por Postgres y se recalculan solas: el número que ve gerencia no
 * puede separarse de las cifras con que se calculó, y esa garantía se pierde en cuanto
 * alguien pueda teclear el total directamente.
 */
class MonthlyEnergyCorrectionService
{
    /**
     * Techos de cordura, a escala de **mes**.
     *
     * El `CHECK` de `production_calendar` es de 2.000 t por día; aquí un mes legítimo de
     * El Pajuil son 5.000–6.800 t y hasta 190.000 kWh. Aplicar el techo diario dejaría
     * fuera todos los meses reales, y no poner ninguno repite el error de los kilogramos
     * a escala mensual.
     */
    private const MAX_MONTHLY_TONS = 100_000;

    private const MAX_MONTHLY_KWH = 10_000_000;

    /**
     * Escribe la corrección y marca el mes como puesto a mano.
     *
     * Las dos banderas no son burocracia: el cierre del día 1 recalcula el mes que acaba
     * de terminar, y sin ellas la corrección duraría hasta esa madrugada. Son las mismas
     * que ya respetan {@see PlantKpiService::snapshotMonth()} para los meses importados.
     *
     * Un campo vacío entra como `null`, no como cero. Es la distinción que sostiene toda
     * la planilla: cero afirma que no hubo consumo, vacío dice que no se sabe.
     *
     * @param  array{processed_tons?: float|string|null, kwh_grid?: float|string|null, kwh_genset?: float|string|null, kwh_turbine?: float|string|null}  $values
     *
     * @throws BusinessRuleException
     */
    public function apply(Plant $plant, int $year, int $month, array $values): PlantMonthlyKpi
    {
        if ($month < 1 || $month > 12) {
            throw new BusinessRuleException('Ese mes no existe.');
        }

        $tons = $this->clean($values['processed_tons'] ?? null, self::MAX_MONTHLY_TONS, 'La fruta del mes');
        $grid = $this->clean($values['kwh_grid'] ?? null, self::MAX_MONTHLY_KWH, 'El consumo de red pública');
        $genset = $this->clean($values['kwh_genset'] ?? null, self::MAX_MONTHLY_KWH, 'El consumo de planta eléctrica');
        $turbine = $this->clean($values['kwh_turbine'] ?? null, self::MAX_MONTHLY_KWH, 'La generación de turbina');

        return DB::transaction(function () use ($plant, $year, $month, $tons, $grid, $genset, $turbine): PlantMonthlyKpi {
            $existing = PlantMonthlyKpi::withoutGlobalScopes()
                ->where('plant_id', $plant->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            return PlantMonthlyKpi::withoutGlobalScopes()->updateOrCreate(
                ['plant_id' => $plant->id, 'year' => $year, 'month' => $month],
                [
                    'tenant_id' => $plant->tenant_id,
                    'processed_tons' => $tons ?? 0,
                    'processed_tons_is_manual' => true,
                    'kwh_grid' => $grid,
                    'kwh_genset' => $genset,
                    'kwh_turbine' => $turbine,
                    'energy_is_imported' => true,
                    'calculated_at' => $existing?->calculated_at ?? now(),
                ],
            )->refresh();
        });
    }

    /**
     * Devuelve el mes a lo que dicen los contadores, quitándole las marcas de manual.
     *
     * Es la vuelta atrás, y sin ella la edición sería una puerta de un solo sentido: una
     * corrección apresurada quedaría clavada para siempre sobre un mes que sí tiene
     * lecturas diarias detrás.
     */
    public function recalculateFromReadings(Plant $plant, int $year, int $month): PlantMonthlyKpi
    {
        return DB::transaction(function () use ($plant, $year, $month): PlantMonthlyKpi {
            PlantMonthlyKpi::withoutGlobalScopes()
                ->where('plant_id', $plant->id)
                ->where('year', $year)
                ->where('month', $month)
                ->update(['processed_tons_is_manual' => false, 'energy_is_imported' => false]);

            return app(PlantKpiService::class)->snapshotMonth($plant, $year, $month);
        });
    }

    /** ¿Este mes tiene lecturas diarias de contador detrás? */
    public function hasDailyReadings(Plant $plant, int $year, int $month): bool
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();

        return EnergyMeterReading::withoutGlobalScopes()
            ->whereBetween('reading_date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()])
            ->whereHas('energyMeter', fn ($q) => $q->where('plant_id', $plant->id))
            ->exists();
    }

    /**
     * @throws BusinessRuleException
     */
    private function clean(float|string|null $raw, float $max, string $etiqueta): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (float) $raw;

        if ($value < 0) {
            throw new BusinessRuleException("{$etiqueta} no puede ser negativa.");
        }

        if ($value > $max) {
            throw new BusinessRuleException(
                "{$etiqueta} da ".number_format($value, 0, ',', '.').', muy por encima de lo que '
                .'una planta registra en un mes. Revisa las unidades.'
            );
        }

        return round($value, 2);
    }
}
