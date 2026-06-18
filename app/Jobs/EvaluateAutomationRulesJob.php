<?php

namespace App\Jobs;

use App\Domain\Automation\Services\AutomationService;
use App\Models\AutomationRule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Evaluates all active, non-disabled automation rules for a single tenant.
 *
 * ShouldBeUnique ensures at most one job per tenant is enqueued or processing,
 * preventing concurrent rule evaluation for the same tenant (race conditions,
 * duplicate MRs, duplicate notifications).
 *
 * Tenants are processed in parallel — the uniqueId is per-tenant, not global.
 */
class EvaluateAutomationRulesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 600;

    public function __construct(public readonly string $tenantId)
    {
        $this->onQueue('automations');
    }

    /** One job per tenant at a time. */
    public function uniqueId(): string
    {
        return "automation-tenant-{$this->tenantId}";
    }

    /** Hold the lock for the full expected processing window. */
    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(AutomationService $service): void
    {
        // withoutGlobalScopes: no HTTP context in queue workers
        $rules = AutomationRule::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('is_active', true)
            ->whereNot('mode', 'disabled')
            ->whereNull('deleted_at')
            ->get();

        foreach ($rules as $rule) {
            $service->executeRule($rule);
        }
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Automation rule evaluation failed', [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
