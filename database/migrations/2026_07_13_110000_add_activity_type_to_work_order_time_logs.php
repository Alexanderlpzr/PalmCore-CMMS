<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2 — el MTTR mide reparar, no esperar.
 *
 * Deliberately nullable with no default. A time log recorded before this column
 * existed was not «reparación»: nobody asked the técnico what he was doing, and
 * backfilling a guess would silently manufacture the very number this column
 * exists to make honest. Unclassified stays unclassified, and the KPI reports
 * `null` rather than inventing wrench time it never measured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_time_logs', function (Blueprint $table) {
            $table->string('activity_type', 30)->nullable()->after('hours');

            // The KPI query: hours per activity across an OT / a window.
            $table->index(['work_order_id', 'activity_type']);
        });
    }

    public function down(): void
    {
        Schema::table('work_order_time_logs', function (Blueprint $table) {
            $table->dropIndex(['work_order_id', 'activity_type']);
            $table->dropColumn('activity_type');
        });
    }
};
