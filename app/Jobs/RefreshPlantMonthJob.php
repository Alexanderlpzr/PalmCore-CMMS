<?php

namespace App\Jobs;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Rehace el cierre de un mes de una planta apenas cambian sus datos: un día de
 * producción o una lectura de energía. Antes solo lo rehacía la corrida de las 04:00, y
 * los días que se cargaban tarde dejaban a «Consumo de energía» con las toneladas de la
 * víspera mientras «Producción» ya las sumaba (el 28/10/2026: 3.126 t contra 4.669,8 t).
 *
 * Espera un minuto y es único por planta y mes: los treinta días de «Programar mes» o
 * una tanda de lecturas terminan en un solo recálculo, con todo ya guardado.
 */
class RefreshPlantMonthJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** Mientras espera, los demás cambios del mismo mes no encolan otro. */
    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $plantId,
        public readonly int $year,
        public readonly int $month,
    ) {
        $this->onQueue('analytics');
    }

    /**
     * Encola el recálculo del mes de esa fecha, si el mes sigue abierto: el en curso o el
     * anterior, los mismos que rehace la corrida diaria. Un mes más viejo ya lo revisó
     * gerencia y se mueve a mano, con `plant-kpis:snapshot`.
     */
    public static function dispatchFor(?string $plantId, ?CarbonInterface $date): void
    {
        if ($plantId === null || $date === null) {
            return;
        }

        // Por año y mes, no por instante: la fecha del día viene en otra zona que «ahora»
        // y el 1.º de octubre a medianoche no era el mismo momento en las dos.
        $month = Carbon::instance($date);
        $current = Carbon::now();
        $months = fn (CarbonInterface $day): int => $day->year * 12 + $day->month;

        if ($months($month) < $months($current) - 1 || $months($month) > $months($current)) {
            return;
        }

        static::dispatch($plantId, $month->year, $month->month)->delay(now()->addMinute());
    }

    /**
     * El candado de «único» en archivos, como RecalculateEquipmentKpisJob: con el de la
     * base, un candado ya tomado aborta la transacción de PostgreSQL (SQLSTATE[25P02]) y
     * se llevaba por delante el guardado del día que lo mandó.
     */
    public function uniqueVia(): Repository
    {
        return Cache::driver('file');
    }

    public function uniqueId(): string
    {
        return "{$this->plantId}|{$this->year}|{$this->month}";
    }

    public function handle(PlantKpiService $service): void
    {
        $plant = Plant::withoutGlobalScopes()->find($this->plantId);

        if ($plant) {
            $service->snapshotMonth($plant, $this->year, $this->month);
        }
    }
}
