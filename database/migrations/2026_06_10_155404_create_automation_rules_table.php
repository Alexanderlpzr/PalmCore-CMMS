<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->references('id')->on('tenants');
            $table->string('name', 100);
            $table->string('event_type', 50);
            $table->string('mode', 20)->default('disabled');
            $table->boolean('is_active')->default(false);
            $table->jsonb('configuration')->nullable();
            $table->timestampTz('last_executed_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'event_type']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
