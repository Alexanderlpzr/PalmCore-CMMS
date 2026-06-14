<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUuid('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->foreignUuid('warehouse_spare_part_id')->constrained('warehouse_spare_parts')->cascadeOnDelete();
            $table->foreignUuid('source_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('destination_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignUuid('work_order_part_id')->nullable()->constrained('work_order_parts')->nullOnDelete();
            $table->string('transaction_number', 30)->unique();
            $table->string('type', 20);
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_cost', 14, 4)->nullable();
            $table->decimal('previous_stock', 12, 4);
            $table->decimal('new_stock', 12, 4);
            $table->string('spare_part_code_snapshot', 50);
            $table->string('spare_part_name_snapshot', 255);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('performed_by')->constrained('users');
            $table->timestampTz('performed_at', 0);
            $table->timestampsTz(0);

            $table->index(['tenant_id', 'type', 'performed_at']);
            $table->index(['warehouse_id', 'spare_part_id', 'performed_at']);
            $table->index(['tenant_id', 'performed_at']);
        });

        // Self-referential FK added after table creation
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignUuid('related_transaction_id')->nullable()->after('warehouse_spare_part_id')
                ->constrained('inventory_transactions')->nullOnDelete();
        });

        // Partial indexes for optional FKs (PostgreSQL only)
        DB::statement('CREATE INDEX idx_inventory_transactions_wo ON inventory_transactions (work_order_id) WHERE work_order_id IS NOT NULL');
        DB::statement('CREATE INDEX idx_inventory_transactions_related ON inventory_transactions (related_transaction_id) WHERE related_transaction_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
