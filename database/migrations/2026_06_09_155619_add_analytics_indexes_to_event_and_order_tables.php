<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supports time-range trend queries: WHERE tenant_id = ? AND started_at >= ?
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->index(['tenant_id', 'started_at'], 'idx_ede_tenant_started_at');
        });

        // Supports cost-by-equipment GROUP BY: WHERE tenant_id = ? GROUP BY equipment_id
        Schema::table('work_orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'equipment_id'], 'idx_wo_tenant_equipment');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->dropIndex('idx_ede_tenant_started_at');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('idx_wo_tenant_equipment');
        });
    }
};
