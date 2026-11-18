<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El ciclo comercial del aceite y del palmiste, que la Directora Administrativa de El
 * Pajuil llevaba en «Proyeccion_vs_Despacho_El Pajuil.xlsx»: los clientes, las
 * transportadoras, las ofertas mercantiles (los contratos) y los despachos —una fila por
 * cargue— con su descargue en el cliente, su factura y su pago.
 *
 * El plan del año (proyección de fruta y de ventas) viene en una fase siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('market', 15);
            $table->string('tax_id', 30)->nullable();
            $table->json('aliases')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('carriers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('tax_id', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('sales_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('product', 10);
            $table->string('number', 40);
            $table->decimal('contracted_tons', 10, 2);
            $table->date('signed_on')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'number']);
            $table->index('customer_id');
        });

        Schema::create('sales_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('product', 10);
            $table->string('status', 15)->default('cargado');

            // El cargue en la planta.
            $table->date('loaded_on');
            $table->string('load_number', 30)->nullable();
            $table->string('plate', 12);
            $table->string('remission_number', 30)->nullable();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('sales_offer_id')->nullable()->constrained('sales_offers')->nullOnDelete();
            $table->foreignUuid('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
            $table->string('market', 15);
            $table->decimal('departure_weight_tons', 8, 2);
            $table->decimal('departure_acidity', 5, 2)->nullable();
            $table->decimal('freight_rate_per_ton', 12, 2)->nullable();

            // El descargue y la liquidación del cliente.
            $table->date('unloaded_on')->nullable();
            $table->string('receipt_number', 30)->nullable();
            $table->decimal('arrival_weight_tons', 8, 2)->nullable();
            $table->decimal('arrival_acidity', 5, 2)->nullable();
            $table->decimal('settlement_amount', 16, 2)->nullable();
            $table->decimal('quality_bonus', 14, 2)->nullable();
            $table->decimal('billed_freight', 14, 2)->nullable();

            // La factura y el pago.
            $table->string('invoice_number', 30)->nullable();
            $table->date('invoiced_on')->nullable();
            $table->decimal('invoice_amount', 16, 2)->nullable();
            $table->date('paid_on')->nullable();

            $table->text('notes')->nullable();
            $table->string('import_key', 120)->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'loaded_on']);
            $table->index('customer_id');
            $table->index('sales_offer_id');
            $table->unique(['tenant_id', 'import_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_dispatches');
        Schema::dropIfExists('sales_offers');
        Schema::dropIfExists('carriers');
        Schema::dropIfExists('customers');
    }
};
