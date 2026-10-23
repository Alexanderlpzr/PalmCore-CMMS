<?php

namespace App\Jobs;

use App\Domain\HumanResources\Services\PresumedWorkdayService;
use App\Infrastructure\Tenancy\CurrentTenant;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * La jornada presunta de cada noche: el administrativo que ayer no marcó en portería
 * trabajó su jornada legal. Mira la última semana y no solo ayer, para que una noche en
 * que el servidor no corrió no deje un día en cero; lo ya llenado no se repite.
 *
 * Solo hace algo en la empresa que tiene prendida la jornada presunta.
 */
class PresumeAdministrativeWorkdaysJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Cuántos días hacia atrás revisa cada noche. */
    public const LOOKBACK_DAYS = 7;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 300;

    public function __construct(public readonly string $tenantId) {}

    public function uniqueId(): string
    {
        return "presumed-workdays-tenant-{$this->tenantId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(PresumedWorkdayService $service): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);

        if (! $tenant) {
            return;
        }

        CurrentTenant::set($tenant);

        try {
            $yesterday = CarbonImmutable::now($tenant->plantTimezone())->subDay();
            $service->fill($tenant, $yesterday->subDays(self::LOOKBACK_DAYS - 1), $yesterday);
        } finally {
            CurrentTenant::clear();
        }
    }
}
