<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Filament's database-notifications topbar counts unread rows with the JSON
     * operator `data->>'format' = 'filament'`. PostgreSQL only allows `->>` on
     * json/jsonb columns, but the stock Laravel notifications migration created
     * `data` as text — so enabling the notification bell threw
     * "operator does not exist: text ->> unknown" on every panel page.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');

            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            $table->json('data')->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');

            return;
        }

        Schema::table('notifications', function (Blueprint $table): void {
            $table->text('data')->change();
        });
    }
};
