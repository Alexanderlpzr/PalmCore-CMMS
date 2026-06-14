<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('idempotency_key', 255);
            $table->string('request_fingerprint', 64);
            $table->smallInteger('response_status');
            $table->jsonb('response_body');
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at');

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'idempotency_key', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
