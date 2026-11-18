<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 del módulo comercial: lo que hay en la planta para vender.
 *
 *  - Los tanques de aceite y la medición diaria de cada uno (toneladas y acidez). La
 *    producción del día no se digita: sale de lo que subieron los tanques más lo que se
 *    despachó.
 *  - La maquila de la almendra: cada cargue que se manda a procesar a un tercero, con la
 *    torta y el aceite que devuelve y lo que cuesta.
 *  - Los cortes de inventario de palmiste (PKO) y de torta.
 *
 * Las hojas «Disponibilidad Aceite», «Almendra y PKO (Maquila)» e «Inventario Palmiste
 * PKO» del Excel de la Directora Administrativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oil_tanks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('capacity_tons', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('oil_tank_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('oil_tank_id')->constrained('oil_tanks')->cascadeOnDelete();
            $table->date('reading_date');
            $table->decimal('level_tons', 10, 2);
            $table->decimal('acidity_percent', 5, 2)->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['oil_tank_id', 'reading_date']);
            $table->index(['tenant_id', 'reading_date']);
        });

        Schema::create('kernel_toll_loads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('loaded_on');
            $table->string('plate', 12)->nullable();
            $table->string('remission_number', 30)->nullable();
            $table->date('unloaded_on')->nullable();
            $table->decimal('departure_weight_tons', 8, 2);
            $table->decimal('arrival_weight_tons', 8, 2)->nullable();
            $table->unsignedInteger('processed_kg')->nullable();
            $table->unsignedInteger('oil_kg')->nullable();
            $table->unsignedInteger('cake_kg')->nullable();
            $table->decimal('toll_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('cake_price_per_ton', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('import_key', 120)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'loaded_on']);
            $table->unique(['tenant_id', 'import_key']);
        });

        Schema::create('kernel_inventory_counts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('counted_on');
            $table->decimal('pko_tons', 10, 2)->nullable();
            $table->decimal('cake_tons', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'counted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kernel_inventory_counts');
        Schema::dropIfExists('kernel_toll_loads');
        Schema::dropIfExists('oil_tank_readings');
        Schema::dropIfExists('oil_tanks');
    }
};
