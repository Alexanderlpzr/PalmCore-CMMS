<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Horómetros do go backwards.
 *
 * The real log shows a meter reading 10.452 h one week and 158 h the next: the
 * instrument was replaced. The system used to *throw* on that, which means the
 * plant could not enter its own data — and a meter-driven preventive program
 * that cannot receive readings is a preventive program that does not run.
 *
 * The fix is to stop treating the number on the dial as the truth. The truth is
 * the *accumulated* hours the machine has worked, which never decreases: a reset
 * simply starts a new dial, and its reading is the consumption since the swap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_meter_readings', function (Blueprint $table) {
            // What the previous dial said. Kept for the audit trail: it is the
            // only way to explain, a year later, why the number dropped.
            $table->decimal('previous_value', 10, 1)->nullable()->after('reading_value');

            // Consumption between this reading and the previous one. On a reset
            // this is the new dial's own reading, not the (negative) difference.
            $table->decimal('delta', 10, 1)->default(0)->after('previous_value');

            // The number the preventive plan must be scheduled against.
            $table->decimal('accumulated_value', 12, 1)->default(0)->after('delta');

            $table->boolean('is_reset')->default(false)->after('accumulated_value');

            $table->index(['equipment_id', 'is_reset']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->decimal('accumulated_meter_reading', 12, 1)->default(0)->after('current_meter_reading');
        });

        // Existing installations never had a reset, so the dial *is* the total.
        DB::statement(
            'UPDATE equipment SET accumulated_meter_reading = COALESCE(current_meter_reading, 0)'
        );
        DB::statement(
            'UPDATE equipment_meter_readings SET accumulated_value = reading_value'
        );
    }

    public function down(): void
    {
        Schema::table('equipment_meter_readings', function (Blueprint $table) {
            $table->dropIndex(['equipment_id', 'is_reset']);
            $table->dropColumn(['previous_value', 'delta', 'accumulated_value', 'is_reset']);
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('accumulated_meter_reading');
        });
    }
};
