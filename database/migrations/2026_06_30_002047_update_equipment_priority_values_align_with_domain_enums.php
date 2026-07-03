<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align EquipmentPriority DB values with the shared domain contract.
     *
     * Before: p1, p2, p3, p4  (5-char column, legacy short format)
     * After:  p1_critical, p2_high, p3_medium, p4_low  (20-char column, matches
     *         WorkOrderPriority, MaintenanceRequestPriority, and design.js PRIORITY map)
     */
    public function up(): void
    {
        // 1. Widen the column before touching data so no value is truncated.
        Schema::table('equipment', function (Blueprint $table): void {
            $table->string('priority', 20)->default('p3_medium')->change();
        });

        // 2. Migrate existing values one-shot with CASE to avoid partial updates.
        DB::statement("
            UPDATE equipment
            SET priority = CASE priority
                WHEN 'p1' THEN 'p1_critical'
                WHEN 'p2' THEN 'p2_high'
                WHEN 'p3' THEN 'p3_medium'
                WHEN 'p4' THEN 'p4_low'
                ELSE priority
            END
            WHERE priority IN ('p1','p2','p3','p4')
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE equipment
            SET priority = CASE priority
                WHEN 'p1_critical' THEN 'p1'
                WHEN 'p2_high'     THEN 'p2'
                WHEN 'p3_medium'   THEN 'p3'
                WHEN 'p4_low'      THEN 'p4'
                ELSE priority
            END
            WHERE priority IN ('p1_critical','p2_high','p3_medium','p4_low')
        ");

        Schema::table('equipment', function (Blueprint $table): void {
            $table->string('priority', 5)->default('p3')->change();
        });
    }
};
