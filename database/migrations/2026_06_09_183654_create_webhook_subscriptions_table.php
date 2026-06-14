<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->index()->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->jsonb('events');
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestampTz('last_triggered_at')->nullable();
            $table->foreignUuid('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};
