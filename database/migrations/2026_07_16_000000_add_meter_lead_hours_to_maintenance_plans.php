<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anticipación de la OT en horas de horómetro (LEAD-1).
 *
 * `grace_meter_hours` ya existía, pero es lo contrario: cuántas horas se tolera
 * pasarse DESPUÉS del vencimiento antes de dar el plan por vencido. Esto es
 * cuántas horas ANTES generar la OT, para tener tiempo de pedir el repuesto:
 * «cambio de aceite cada 5000 h, pero la orden aparece a las 4800 h».
 *
 * Nullable a propósito: un plan sin anticipación configurada usa el default de
 * 200 h que vive en PreventiveWorkOrderGenerator, no una columna con un número
 * mágico repetido en cada fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->integer('meter_lead_hours')->nullable()->unsigned()->after('grace_meter_hours');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropColumn('meter_lead_hours');
        });
    }
};
