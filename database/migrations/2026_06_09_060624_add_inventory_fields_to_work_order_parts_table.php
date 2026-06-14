<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_parts', function (Blueprint $table) {
            $table->foreignUuid('spare_part_id')
                ->nullable()
                ->after('work_order_id')
                ->nullOnDelete()
                ->constrained('spare_parts');

            $table->foreignUuid('warehouse_id')
                ->nullable()
                ->after('spare_part_id')
                ->nullOnDelete()
                ->constrained('warehouses');

            $table->string('status', 20)->default('requested')->after('warehouse_id');

            $table->decimal('reserved_quantity', 12, 4)->default(0)->after('status');
            $table->decimal('issued_quantity', 12, 4)->default(0)->after('reserved_quantity');
            $table->decimal('returned_quantity', 12, 4)->default(0)->after('issued_quantity');

            // Locked at part-addition time — never recalculated from spare_parts for cost history
            $table->decimal('unit_cost_snapshot', 12, 4)->nullable()->after('returned_quantity');

            $table->index(['work_order_id', 'status']);
            $table->index(['spare_part_id', 'status']);
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_parts', function (Blueprint $table) {
            $table->dropIndex(['work_order_id', 'status']);
            $table->dropIndex(['spare_part_id', 'status']);
            $table->dropIndex(['warehouse_id']);

            $table->dropConstrainedForeignId('spare_part_id');
            $table->dropConstrainedForeignId('warehouse_id');

            $table->dropColumn([
                'status',
                'reserved_quantity',
                'issued_quantity',
                'returned_quantity',
                'unit_cost_snapshot',
            ]);
        });
    }
};
