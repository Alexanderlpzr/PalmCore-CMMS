<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->string('last_error', 500)->nullable()->after('last_triggered_at');
        });

        // GIN index for fast jsonb containment: WHERE events @> '["event.name"]'
        DB::statement('CREATE INDEX webhook_subscriptions_events_gin ON webhook_subscriptions USING gin (events)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS webhook_subscriptions_events_gin');

        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dropColumn('last_error');
        });
    }
};
