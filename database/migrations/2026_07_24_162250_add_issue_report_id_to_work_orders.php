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
        Schema::table('work_orders', function (Blueprint $table) {
            // La OT puede nacer de un reporte de novedad: se enlaza para la
            // trazabilidad reporte → OT → solución. Nullable: las OT sueltas no lo tienen.
            $table->foreignUuid('issue_report_id')
                ->nullable()
                ->after('maintenance_request_id')
                ->constrained('equipment_issue_reports')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issue_report_id');
        });
    }
};
