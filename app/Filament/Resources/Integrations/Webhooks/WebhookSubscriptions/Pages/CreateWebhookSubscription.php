<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages;

use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\WebhookSubscriptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWebhookSubscription extends CreateRecord
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
