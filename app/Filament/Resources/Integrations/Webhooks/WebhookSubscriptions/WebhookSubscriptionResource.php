<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions;

use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages\CreateWebhookSubscription;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages\EditWebhookSubscription;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages\ListWebhookSubscriptions;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Pages\ViewWebhookSubscription;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Schemas\WebhookSubscriptionForm;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Schemas\WebhookSubscriptionInfolist;
use App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Tables\WebhookSubscriptionsTable;
use App\Models\WebhookSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WebhookSubscriptionResource extends Resource
{
    protected static ?string $model = WebhookSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $modelLabel = 'Webhook';

    protected static ?string $pluralModelLabel = 'Webhooks';

    protected static string|UnitEnum|null $navigationGroup = 'Integraciones';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;

    public static function form(Schema $schema): Schema
    {
        return WebhookSubscriptionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return WebhookSubscriptionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebhookSubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookSubscriptions::route('/'),
            'create' => CreateWebhookSubscription::route('/create'),
            'view' => ViewWebhookSubscription::route('/{record}'),
            'edit' => EditWebhookSubscription::route('/{record}/edit'),
        ];
    }
}
