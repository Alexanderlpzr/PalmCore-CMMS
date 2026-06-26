<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_components', function (Blueprint $table): void {
            // ── Identity ──────────────────────────────────────────────────────────
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            // Self-referential FK for future tree support (not implemented yet)
            $table->uuid('parent_id')->nullable()->index();

            // ── Identification ────────────────────────────────────────────────────
            $table->string('code', 50)->nullable();
            $table->string('name', 255);
            $table->string('manufacturer', 255)->nullable();
            $table->string('model', 255)->nullable();
            $table->string('serial_number', 255)->nullable();

            // ── Classification ────────────────────────────────────────────────────
            $table->string('criticality', 20)->default('medium');

            // ── Technical ─────────────────────────────────────────────────────────
            $table->unsignedInteger('useful_life_hours')->nullable();

            // ── Notes ─────────────────────────────────────────────────────────────
            $table->text('notes')->nullable();

            // ── Timestamps ────────────────────────────────────────────────────────
            $table->timestampsTz();
            $table->softDeletesTz();

            // ── Constraints ───────────────────────────────────────────────────────
            $table->unique(['equipment_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_components');
    }
};
