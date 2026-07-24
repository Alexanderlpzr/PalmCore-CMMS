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
            // Columnas de la planilla de OT del cliente: proceso, área de mtto,
            // ejecutante (cuadrilla, texto libre) y horómetro al momento del trabajo.
            $table->string('process', 30)->nullable()->after('area_id');
            $table->string('maintenance_area', 20)->nullable()->after('work_order_type');
            $table->string('executed_by', 255)->nullable()->after('assigned_supervisor');
            $table->decimal('meter_reading', 12, 1)->nullable()->after('actual_labor_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn(['process', 'maintenance_area', 'executed_by', 'meter_reading']);
        });
    }
};
