<?php

namespace App\Jobs;

use App\Domain\Maintenance\Services\PreventiveWorkOrderGenerator;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Generates the preventive work orders a tenant owes, once a day.
 *
 * ShouldBeUnique guarantees a single run per tenant at a time; the generator
 * itself refuses to create a second OT for a plan that already has one open, so
 * running this job twice in a day is harmless.
 */
class GeneratePreventiveWorkOrdersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 600;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $leadDays = 7,
    ) {
        $this->onQueue('maintenance');
    }

    public function uniqueId(): string
    {
        return "preventive-generation-tenant-{$this->tenantId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(PreventiveWorkOrderGenerator $generator): void
    {
        $actor = $this->resolveActor();

        if ($actor === null) {
            logger()->warning('Sin usuario responsable para generar preventivos.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $result = $generator->generateForTenant($this->tenantId, $actor, $this->leadDays);

        logger()->info('Generación de preventivos completada.', [
            'tenant_id' => $this->tenantId,
            'generated' => $result['generated'],
            'skipped' => $result['skipped'],
        ]);
    }

    /**
     * Queue workers have no authenticated user, so the OTs are created on behalf
     * of the tenant's owner — a real, auditable person, not a synthetic account.
     */
    private function resolveActor(): ?User
    {
        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);

        if ($tenant === null) {
            return null;
        }

        return $tenant->users()->wherePivot('is_owner', true)->first()
            ?? $tenant->users()->first();
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Falló la generación de OT preventivas.', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
