<?php

namespace App\Contracts;

interface WebhookableEvent
{
    public function webhookEventName(): string;

    /** @return array<string, mixed> */
    public function webhookPayload(): array;

    public function webhookTenantId(): string;
}
