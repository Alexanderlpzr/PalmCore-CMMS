<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages;

use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\WebhookSubscriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWebhookSubscription extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = WebhookSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->getBackAction(),
        ];
    }
}
