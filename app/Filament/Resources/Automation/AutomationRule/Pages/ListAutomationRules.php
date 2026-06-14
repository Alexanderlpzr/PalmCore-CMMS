<?php

namespace App\Filament\Resources\Automation\AutomationRule\Pages;

use App\Domain\Automation\Enums\AutomationEventType;
use App\Domain\Automation\Enums\AutomationMode;
use App\Filament\Resources\Automation\AutomationRule\AutomationRuleResource;
use App\Models\AutomationRule;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListAutomationRules extends ListRecords
{
    protected static string $resource = AutomationRuleResource::class;

    public function mount(): void
    {
        parent::mount();
        $this->ensureDefaultRulesExist();
    }

    /** Seed all 5 default rules for this tenant if they don't exist yet. */
    private function ensureDefaultRulesExist(): void
    {
        $tenantId = Filament::getTenant()->id;

        foreach (AutomationEventType::cases() as $eventType) {
            AutomationRule::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'event_type' => $eventType->value],
                [
                    'name' => $eventType->label(),
                    'mode' => AutomationMode::Disabled->value,
                    'is_active' => false,
                    'configuration' => $eventType->defaultConfiguration() ?: null,
                ],
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
