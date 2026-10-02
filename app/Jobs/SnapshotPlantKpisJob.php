<?php

namespace App\Jobs;

use App\Console\Commands\SnapshotPlantKpis;
use App\Domain\Analytics\Services\PlantKpiService;
use App\Models\Plant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Mantiene al día el cierre de los meses que todavía se están llenando.
 *
 * Sin mes indicado cierra **el mes en curso y el anterior**, y corre a diario.
 * Antes cerraba solo el mes vencido, una vez, a las 04:00 del día 1 — y eso
 * garantizaba que el número congelado fuera el de antes de los últimos apuntes:
 * el calendario de producción se termina de llenar después de que el mes acaba y
 * los paros se registran tarde. Agosto quedó congelado con 291,6 horas
 * programadas cuando en realidad fueron 418,6, y nada volvía a mirarlo.
 *
 * `snapshotMonth()` es un upsert, así que repetirlo corrige el mes en vez de
 * crear una segunda fila que lo contradiga. De dos meses hacia atrás el cierre
 * se queda quieto a propósito: para mover un mes que gerencia ya revisó está
 * {@see SnapshotPlantKpis}.
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
        $plants = Plant::withoutGlobalScopes()->get();

        foreach ($this->periods() as $period) {
            foreach ($plants as $plant) {
                $service->snapshotMonth($plant, (int) $period->year, (int) $period->month);
            }
        }
    }

    /**
     * El mes pedido, o los dos que siguen abiertos a efectos prácticos.
     *
     * @return list<Carbon>
     */
    private function periods(): array
    {
        if ($this->year !== null && $this->month !== null) {
            return [Carbon::create($this->year, $this->month, 1)->startOfMonth()];
        }

        $current = Carbon::now()->startOfMonth();

        return [$current->copy()->subMonthNoOverflow(), $current];
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Falló el cierre mensual de KPIs de planta.', [
            'error' => $exception->getMessage(),
        ]);
    }
}
