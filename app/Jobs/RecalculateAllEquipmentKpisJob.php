<?php

namespace App\Jobs;

use App\Models\Equipment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Nightly recovery job: fans out RecalculateEquipmentKpisJob for every
 * active equipment in a tenant (or all tenants when tenantId is null).
 *
 * Dispatched by the scheduler as a safety net to fix any stale KPIs that
 * were not recalculated by event-driven triggers (e.g. queue failure, timing
 * race between markStale and ShouldBeUnique lock release).
 */
class RecalculateAllEquipmentKpisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60];

    /**
     * Fan-out dispatches only — actual KPI work is done by child RecalculateEquipmentKpisJob
     * instances which are individually retryable. 120s is sufficient for even very large fleets.
     */
    public int $timeout = 120;

    /**
     * @param  string|null  $tenantId  null = recalculate for ALL tenants
     */
    public function __construct(public readonly ?string $tenantId = null) {}

    public function handle(): void
    {
        $query = Equipment::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->select(['id']);

        if ($this->tenantId !== null) {
            $query->where('tenant_id', $this->tenantId);
        }

        // Fan out individual jobs — each is retryable and deduplicates via ShouldBeUnique
        $query->each(function (Equipment $equipment): void {
            RecalculateEquipmentKpisJob::dispatch($equipment->id);
        });
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Bulk KPI recalculation failed', [
            'tenant_id' => $this->tenantId ?? 'all',
            'error' => $exception->getMessage(),
        ]);
    }
}
