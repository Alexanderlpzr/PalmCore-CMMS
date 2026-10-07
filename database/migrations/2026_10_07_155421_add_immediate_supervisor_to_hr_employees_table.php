<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El jefe inmediato del trabajador: es quien firma el «Vo. Bo.» de su formato de horas
 * extras (TH-FOR-002). En el libro de Excel va en la fila 7 de cada hoja; aquí, en la ficha.
 * Es texto y no un usuario del sistema porque varios jefes de planta no tienen cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('immediate_supervisor', 120)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn('immediate_supervisor');
        });
    }
};
