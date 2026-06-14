<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table) {
            $table->dropColumn('response_body');
            $table->unsignedInteger('response_size')->nullable()->after('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_delivery_logs', function (Blueprint $table) {
            $table->dropColumn('response_size');
            $table->string('response_body', 500)->nullable()->after('duration_ms');
        });
    }
};
