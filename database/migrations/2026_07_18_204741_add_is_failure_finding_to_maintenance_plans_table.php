<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una tarea de búsqueda de falla (failure-finding task) es, mecánicamente, un
 * plan preventivo normal — genera OT tipo Preventive igual que cualquier
 * otro, por calendario u horómetro. Este flag solo la distingue en UI y
 * reportes como "existe para revelar una falla oculta", sin tocar el motor
 * de generación (PreventiveWorkOrderGenerator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->boolean('is_failure_finding')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropColumn('is_failure_finding');
        });
    }
};
