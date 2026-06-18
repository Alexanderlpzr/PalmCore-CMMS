<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `secret` column was created as varchar(64) but the model encrypts it
     * with Laravel's `encrypted` cast, producing values far longer than 64 chars.
     * Widening to text fixes the incompatibility without touching existing rows.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE webhook_subscriptions ALTER COLUMN secret TYPE text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE webhook_subscriptions ALTER COLUMN secret TYPE varchar(64)');
    }
};
