<?php

namespace App\Jobs;

use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Freezes the month that just closed for every plant.
 *
 * snapshotMonth() is an upsert, so a paro entered late corrects the month instead
 * of creating a second, contradictory row.
 */
class SnapshotPlantKpisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly ?int $year = null,
        public readonly ?int $month = null,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(PlantKpiService $service): void
    {
        $period = $this->year !== null && $this->month !== null
            ? Carbon::create($this->year, $this->month, 1)
            : now()->subMonthNoOverflow();

        Plant::withoutGlobalScopes()
            ->get()
            ->each(fn (Plant $plant) => $service->snapshotMonth(
                $plant,
                (int) $period->year,
                (int) $period->month,
            ));
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Falló el cierre mensual de KPIs de planta.', [
            'error' => $exception->getMessage(),
        ]);
    }
}
