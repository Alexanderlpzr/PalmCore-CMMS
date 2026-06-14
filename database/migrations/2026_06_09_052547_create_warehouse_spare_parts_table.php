<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_spare_parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignUuid('spare_part_id')->constrained('spare_parts')->cascadeOnDelete();
            $table->decimal('current_stock', 12, 4)->default(0);
            $table->decimal('reserved_stock', 12, 4)->default(0);
            $table->decimal('average_unit_cost', 12, 4)->nullable();
            $table->string('bin_location', 50)->nullable();
            $table->foreignUuid('last_counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_counted_at', 0)->nullable();
            $table->timestampsTz(0);

            $table->unique(['warehouse_id', 'spare_part_id']);
            $table->index(['tenant_id', 'spare_part_id']);
            $table->index(['tenant_id', 'warehouse_id']);
        });

        DB::statement('ALTER TABLE warehouse_spare_parts ADD CONSTRAINT chk_wsp_current_stock_non_negative CHECK (current_stock >= 0)');
        DB::statement('ALTER TABLE warehouse_spare_parts ADD CONSTRAINT chk_wsp_reserved_stock_non_negative CHECK (reserved_stock >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_spare_parts');
    }
};
