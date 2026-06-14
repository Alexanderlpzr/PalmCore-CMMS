<?php

namespace App\Console\Commands;

use App\Domain\Automation\Enums\AutomationEventType;
use App\Domain\Automation\Enums\AutomationMode;
use App\Domain\Automation\Services\AutomationService;
use App\Models\AutomationRule;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-overdue-maintenance')]
#[Description('Notify responsible users of overdue maintenance plans')]
class SendOverdueMaintenanceNotificationsCommand extends Command
{
    public function handle(AutomationService $automationService): int
    {
        $tenants = Tenant::withoutGlobalScopes()
            ->where('is_active', true)
            ->get();

        $count = 0;

        foreach ($tenants as $tenant) {
            // Find or create the maintenance_plan_overdue rule for this tenant.
            // Defaults to NotifyOnly + active so existing behaviour is preserved.
            $rule = AutomationRule::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('event_type', AutomationEventType::MaintenancePlanOverdue->value)
                ->whereNull('deleted_at')
                ->first();

            if ($rule === null) {
                $rule = AutomationRule::forceCreate([
                    'tenant_id' => $tenant->id,
                    'name' => AutomationEventType::MaintenancePlanOverdue->label(),
                    'event_type' => AutomationEventType::MaintenancePlanOverdue->value,
                    'mode' => AutomationMode::NotifyOnly->value,
                    'is_active' => true,
                ]);
            }

            if (! $rule->isActionable()) {
                continue;
            }

            $automationService->executeRule($rule);
            $count++;
        }

        $this->info("Processed overdue maintenance notifications for {$count} tenant(s).");

        return Command::SUCCESS;
    }
}
