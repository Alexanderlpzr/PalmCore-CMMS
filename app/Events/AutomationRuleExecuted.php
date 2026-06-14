<?php

namespace App\Events;

use App\Contracts\WebhookableEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\AutomationRule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AutomationRuleExecuted implements WebhookableEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly AutomationRule $rule) {}

    public function webhookEventName(): string
    {
        return WebhookEvent::AutomationExecuted->value;
    }

    public function webhookPayload(): array
    {
        return [
            'id' => $this->rule->id,
            'tenant_id' => $this->rule->tenant_id,
            'name' => $this->rule->name,
            'event_type' => $this->rule->event_type?->value,
            'executed_at' => now()->toIso8601String(),
        ];
    }

    public function webhookTenantId(): string
    {
        return $this->rule->tenant_id;
    }
}
