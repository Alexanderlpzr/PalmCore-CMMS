<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX idx_work_orders_tenant_completed_at_active
            ON work_orders (tenant_id, completed_at)
            WHERE deleted_at IS NULL');

        DB::statement('CREATE INDEX idx_work_orders_tenant_type_active
            ON work_orders (tenant_id, work_order_type)
            WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_work_orders_tenant_completed_at_active');
        DB::statement('DROP INDEX IF EXISTS idx_work_orders_tenant_type_active');
    }
};
