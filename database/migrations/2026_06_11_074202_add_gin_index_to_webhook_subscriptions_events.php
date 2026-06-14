<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // GIN index accelerates `whereJsonContains('events', $eventName)` in WebhookDispatcher.
        // jsonb_path_ops is smaller than jsonb_ops and supports the @> operator used by Laravel.
        DB::statement('
            CREATE INDEX IF NOT EXISTS idx_webhook_subscriptions_events_gin
            ON webhook_subscriptions
            USING gin (events jsonb_path_ops)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_webhook_subscriptions_events_gin');
    }
};
