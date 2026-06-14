<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->index()->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('method', 10);
            $table->string('path', 512);
            $table->smallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
