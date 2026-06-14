<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->references('id')->on('tenants');

            $table->string('severity', 20);
            $table->string('category', 30);
            $table->string('title', 150);
            $table->text('message')->nullable();

            $table->string('entity_type', 50)->nullable();
            $table->uuid('entity_id')->nullable();

            $table->string('status', 20)->default('open');
            $table->timestampTz('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->references('id')->on('users');

            $table->jsonb('metadata')->nullable();

            $table->timestampTz('created_at');
            $table->softDeletesTz();

            $table->index(['tenant_id', 'status', 'severity', 'created_at']);
            $table->index(['tenant_id', 'status', 'category']);
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
        });

        // Una sola alerta abierta por (tenant, entity_type, entity_id, category)
        // Índice parcial — solo cuando status = 'open' y no está eliminada suave
        DB::statement("
            CREATE UNIQUE INDEX alerts_open_idempotency
            ON alerts (tenant_id, entity_type, entity_id, category)
            WHERE status = 'open' AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
