<?php

namespace App\Observers;

use App\Jobs\RefreshPlantMonthJob;
use App\Models\EnergyMeter;
use App\Models\EnergyMeterReading;
use App\Models\ProductionCalendarDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Los días de producción y las lecturas de energía mandan a rehacer el cierre de su mes
 * (RefreshPlantMonthJob), para que «Consumo de energía» y los indicadores mensuales no
 * esperen a la corrida de las 04:00.
 */
class PlantMonthCloseObserver
{
    public function saved(Model $model): void
    {
        $this->refresh($model);

        // Si el día o la lectura cambió de fecha, también se rehace el mes de donde salió.
        $dateColumn = $this->dateColumn($model);

        if ($model->wasChanged($dateColumn) && $model->getOriginal($dateColumn) !== null) {
            RefreshPlantMonthJob::dispatchFor($this->plantId($model), Carbon::parse($model->getOriginal($dateColumn)));
        }
    }

    public function deleted(Model $model): void
    {
        $this->refresh($model);
    }

    private function refresh(Model $model): void
    {
        RefreshPlantMonthJob::dispatchFor($this->plantId($model), $this->date($model));
    }

    private function plantId(Model $model): ?string
    {
        return match (true) {
            $model instanceof ProductionCalendarDay => $model->plant_id,
            $model instanceof EnergyMeterReading => EnergyMeter::withoutGlobalScopes()->whereKey($model->energy_meter_id)->value('plant_id'),
            default => null,
        };
    }

    private function date(Model $model): ?CarbonInterface
    {
        $value = $model->{$this->dateColumn($model)};

        return $value === null ? null : Carbon::parse($value);
    }

    private function dateColumn(Model $model): string
    {
        return $model instanceof EnergyMeterReading ? 'reading_date' : 'calendar_date';
    }
}
