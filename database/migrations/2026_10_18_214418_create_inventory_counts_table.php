<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los conteos de inventario: una jornada de conteo de un almacén —todo, por ubicación o
 * lo que ya toca contar según su ABC— con lo que el sistema decía al empezar y lo que se
 * encontró. Al aplicarlo, cada diferencia queda como ajuste en el kardex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('count_number', 30);
            $table->string('status', 20)->default('open');
            $table->string('scope', 20);
            $table->string('location_filter', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('applied_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'count_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('inventory_count_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('inventory_count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignUuid('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->string('bin_location', 50)->nullable();
            $table->decimal('expected_quantity', 12, 4);
            $table->decimal('counted_quantity', 12, 4)->nullable();
            $table->foreignUuid('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('counted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['inventory_count_id', 'spare_part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_lines');
        Schema::dropIfExists('inventory_counts');
    }
};
