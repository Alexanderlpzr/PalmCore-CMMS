<?php

namespace App\Jobs;

use App\Domain\Reliability\Services\EquipmentKpiService;
use App\Models\Equipment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Recalculates KPIs for a single equipment.
 *
 * ShouldBeUnique ensures at most one job per equipment_id is enqueued or
 * processing at any time, coalescing bursts of rapid events into one run.
 * The nightly RecalculateAllEquipmentKpisJob catches any stale KPIs that
 * slipped through the uniqueness window.
 */
class RecalculateEquipmentKpisJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $equipmentId) {}

    /** Unique key: one job per equipment, regardless of tenant. */
    public function uniqueId(): string
    {
        return $this->equipmentId;
    }

    /**
     * Hold the unique lock for up to 5 minutes.
     * If the job has not completed within this window, a new dispatch is allowed.
     */
    public function uniqueFor(): int
    {
        return 300;
    }

    public function handle(EquipmentKpiService $service): void
    {
        // withoutGlobalScopes: no tenant context in queue workers
        $equipment = Equipment::withoutGlobalScopes()->find($this->equipmentId);

        if ($equipment === null) {
            return; // Equipment was deleted — nothing to recalculate
        }

        $service->recalculate($equipment);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('KPI recalculation failed', [
            'equipment_id' => $this->equipmentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
