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
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('po_number', 40);
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();

            $table->string('status', 30)->default('draft');
            $table->char('currency_code', 3)->default('COP');
            $table->decimal('total', 14, 2)->default(0);

            $table->date('expected_at')->nullable();
            $table->timestampTz('ordered_at', 0)->nullable();   // sent to supplier
            $table->timestampTz('received_at', 0)->nullable();  // fully received
            $table->text('notes')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletesTz('deleted_at', 0);
            $table->timestampsTz(0);

            $table->unique(['tenant_id', 'po_number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
