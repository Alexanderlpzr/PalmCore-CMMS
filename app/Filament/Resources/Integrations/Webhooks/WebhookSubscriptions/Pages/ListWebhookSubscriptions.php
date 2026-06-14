<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages;

use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\WebhookSubscriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWebhookSubscriptions extends ListRecords
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
