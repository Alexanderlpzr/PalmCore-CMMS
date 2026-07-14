<?php

namespace App\Jobs;

use App\Domain\Maintenance\Services\StaleMeterReadingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A7 — busca los horómetros que se quedaron mudos, una vez al día.
 *
 * Corre antes que el generador de preventivos: si un plan por horas no va a
 * disparar porque nadie lee el equipo, que se sepa la misma mañana y no el día
 * que la máquina se rompe.
 */
class DetectStaleMeterReadingsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(public readonly string $tenantId)
    {
        $this->onQueue('maintenance');
    }

    public function uniqueId(): string
    {
        return "stale-meters-tenant-{$this->tenantId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(StaleMeterReadingService $service): void
    {
        $service->raiseAlerts($this->tenantId);
    }
}
