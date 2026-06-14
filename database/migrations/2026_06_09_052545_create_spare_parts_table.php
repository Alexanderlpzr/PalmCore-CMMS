<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('category_type', 30);
            $table->string('criticality', 20);
            $table->string('abc_classification', 1)->nullable();
            $table->string('unit', 20);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('minimum_stock', 12, 4)->nullable();
            $table->decimal('maximum_stock', 12, 4)->nullable();
            $table->decimal('reorder_point', 12, 4)->nullable();
            $table->decimal('reorder_quantity', 12, 4)->nullable();
            $table->smallInteger('lead_time_days')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
            $table->softDeletesTz('deleted_at', 0);
            $table->timestampsTz(0);

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'category_type']);
            $table->index(['tenant_id', 'criticality', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_parts');
    }
};
