<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('impersonator_id')->index()->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('impersonated_user_id')->index()->references('id')->on('users')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->nullable()->index()->references('id')->on('tenants')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->text('reason')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};
