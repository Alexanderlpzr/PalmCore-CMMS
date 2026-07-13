<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paros are facts about the plant, not side effects of a work order.
 *
 * El Pajuil records ~700 stoppages a year and roughly 70% of them never produce
 * an OT (falta de fruta, corte de energía, atascos, esperas de proceso). The
 * original table could not express any of them: it required an equipment, it
 * allowed at most one event per OT, and its only classification was the OT type.
 *
 * This migration makes the table able to hold the real Tipo I / Tipo II taxonomy
 * the plant already uses on paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            // One OT can stop the line more than once (paro, arranque, paro again).
            $table->dropUnique(['work_order_id']);
            $table->index('work_order_id');

            // A paro de planta (falta de fruta, corte de energía) has no equipment.
            $table->foreignUuid('plant_id')->nullable()->after('tenant_id')
                ->constrained('plants')->cascadeOnDelete();

            // ── Real taxonomy: Tipo I (macro) × Tipo II (specific cause) ──
            $table->string('stoppage_category', 30)->nullable()->after('cause_type');
            $table->string('stoppage_cause', 120)->nullable()->after('stoppage_category');

            // Does this stoppage subtract from the plant's programmed hours?
            // A failure recorded while the line kept running does not.
            $table->boolean('affects_production')->default(true)->after('was_planned');

            // Where the record came from and who is accountable for it.
            $table->string('source', 20)->default('manual')->after('affects_production');
            $table->foreignUuid('reported_by')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('registered_by')->nullable()->after('reported_by')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->uuid('equipment_id')->nullable()->change();
        });

        DB::statement(
            'UPDATE equipment_downtime_events e
             SET plant_id = q.plant_id
             FROM equipment q
             WHERE e.equipment_id = q.id AND e.plant_id IS NULL'
        );

        // An event that names neither an equipment nor a plant is not a fact,
        // it is noise. The database refuses it.
        DB::statement(
            'ALTER TABLE equipment_downtime_events
             ADD CONSTRAINT downtime_events_target_check
             CHECK (equipment_id IS NOT NULL OR plant_id IS NOT NULL)'
        );

        // The plant-efficiency query: unplanned production hours lost per month.
        DB::statement(
            'CREATE INDEX equipment_downtime_events_plant_window_idx
             ON equipment_downtime_events (plant_id, started_at)
             WHERE affects_production'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS equipment_downtime_events_plant_window_idx');
        DB::statement('ALTER TABLE equipment_downtime_events DROP CONSTRAINT IF EXISTS downtime_events_target_check');

        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plant_id');
            $table->dropConstrainedForeignId('reported_by');
            $table->dropConstrainedForeignId('registered_by');
            $table->dropColumn(['stoppage_category', 'stoppage_cause', 'affects_production', 'source']);
            $table->dropIndex(['work_order_id']);
            $table->unique('work_order_id');
        });
    }
};
