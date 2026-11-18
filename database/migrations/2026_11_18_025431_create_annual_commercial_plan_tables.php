<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El plan del año de la Directora Administrativa —fase 2 del módulo comercial—: cuánta
 * fruta entregará cada proveedor, qué aceite y palmiste saldrá de ella y cuánto se le
 * venderá a cada cliente, mes a mes. Y la fruta que de verdad llegó.
 *
 * El plan tiene dos versiones por año: el «original», que se aprueba y queda congelado, y
 * la «reproyección», que nace de él y se ajusta cuando el año cambia. El Excel pisaba la
 * proyección con lo real y perdía qué se esperaba al empezar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fruit_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('annual_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('version', 15);
            $table->decimal('opening_oil_tons', 10, 2)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'year', 'version']);
        });

        Schema::create('annual_plan_months', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('annual_plan_id')->constrained('annual_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('plant_capacity_tons', 10, 2)->nullable();
            $table->decimal('tea_percent', 5, 2)->nullable();
            $table->decimal('kernel_percent', 5, 2)->default(5);
            $table->decimal('pko_percent', 5, 2)->default(43);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['annual_plan_id', 'month']);
        });

        Schema::create('annual_plan_fruit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('annual_plan_id')->constrained('annual_plans')->cascadeOnDelete();
            $table->foreignUuid('fruit_supplier_id')->constrained('fruit_suppliers')->restrictOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('tons', 10, 2);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['annual_plan_id', 'fruit_supplier_id', 'month']);
        });

        Schema::create('annual_plan_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('annual_plan_id')->constrained('annual_plans')->cascadeOnDelete();
            // Sin cliente: «Por concretar», lo que se espera vender sin comprador todavía.
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('product', 10);
            $table->unsignedTinyInteger('month');
            $table->decimal('tons', 10, 2);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['annual_plan_id', 'product']);
        });

        Schema::create('fruit_receptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('fruit_supplier_id')->constrained('fruit_suppliers')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('tons', 10, 2);
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'fruit_supplier_id', 'year', 'month']);
            $table->index(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fruit_receptions');
        Schema::dropIfExists('annual_plan_sales');
        Schema::dropIfExists('annual_plan_fruit');
        Schema::dropIfExists('annual_plan_months');
        Schema::dropIfExists('annual_plans');
        Schema::dropIfExists('fruit_suppliers');
    }
};
