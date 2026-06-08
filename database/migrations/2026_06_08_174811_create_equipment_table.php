<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table): void {
            // ── Identity ─────────────────────────────────────────────────────
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->constrained('plants')->restrictOnDelete();
            $table->foreignUuid('area_id')->constrained('areas')->restrictOnDelete();
            $table->uuid('category_id')->nullable();
            $table->uuid('manufacturer_id')->nullable();
            $table->uuid('supplier_id')->nullable();
            $table->uuid('parent_equipment_id')->nullable();

            // ── Identification ────────────────────────────────────────────────
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('model', 255)->nullable();
            $table->string('serial_number', 255)->nullable();
            $table->string('asset_tag', 100)->nullable();

            // ── Status & Classification ───────────────────────────────────────
            $table->string('status', 30)->default('active');
            $table->string('criticality', 20)->default('medium');
            $table->string('priority', 5)->default('p3');

            // ── Lifecycle Dates ───────────────────────────────────────────────
            $table->date('purchase_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->date('commissioning_date')->nullable();
            $table->date('warranty_expiry_date')->nullable();

            // ── Financial ────────────────────────────────────────────────────
            $table->decimal('useful_life_years', 5, 2)->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('replacement_cost', 15, 2)->nullable();
            $table->char('currency_code', 3)->default('USD');

            // ── Location ─────────────────────────────────────────────────────
            $table->string('location_notes', 500)->nullable();

            // ── Technical Specifications (flexible per category) ──────────────
            $table->jsonb('technical_specs')->nullable();

            // ── Notes ────────────────────────────────────────────────────────
            $table->text('notes')->nullable();

            // ── Lifecycle Control ─────────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->timestampTz('retired_at')->nullable();
            $table->text('retired_reason')->nullable();

            // ── Audit ─────────────────────────────────────────────────────────
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            // ── Constraints ───────────────────────────────────────────────────
            $table->unique(['tenant_id', 'code']);
        });

        // ── Foreign Keys (added after table exists for self-ref) ──────────────
        Schema::table('equipment', function (Blueprint $table): void {
            $table->foreign('category_id')
                ->references('id')->on('equipment_categories')
                ->nullOnDelete();

            $table->foreign('manufacturer_id')
                ->references('id')->on('manufacturers')
                ->nullOnDelete();

            $table->foreign('supplier_id')
                ->references('id')->on('suppliers')
                ->nullOnDelete();

            $table->foreign('parent_equipment_id')
                ->references('id')->on('equipment')
                ->nullOnDelete();
        });

        // ── Composite Indexes ─────────────────────────────────────────────────
        DB::statement('CREATE INDEX equipment_tenant_status_criticality_idx ON equipment (tenant_id, status, criticality)');
        DB::statement('CREATE INDEX equipment_tenant_area_idx ON equipment (tenant_id, area_id)');
        DB::statement('CREATE INDEX equipment_tenant_plant_idx ON equipment (tenant_id, plant_id)');
        DB::statement('CREATE INDEX equipment_tenant_category_idx ON equipment (tenant_id, category_id)');

        // Partial index: only active equipment (the common query path)
        DB::statement('CREATE INDEX equipment_active_idx ON equipment (tenant_id, status, priority) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
