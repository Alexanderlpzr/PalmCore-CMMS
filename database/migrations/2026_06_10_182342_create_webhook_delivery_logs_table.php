<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_delivery_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('webhook_subscription_id')->references('id')->on('webhook_subscriptions')->cascadeOnDelete();
            $table->string('event_id', 36);
            $table->string('event_name', 100);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedSmallInteger('duration_ms')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('response_body', 500)->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->index(['webhook_subscription_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_logs');
    }
};
