<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bonificaciones que se pagan por los días laborados, no completas.
 *
 * El bono de rodamiento de El Pajuil es así en su libro de nómina: 367.849 a quien
 * trabajó el mes entero, y 11/30, 15/30 o 24/30 de eso a quien entró a mitad de mes o
 * estuvo incapacitado. Pagarlo completo a todos sobrepaga cada ausencia.
 *
 * Los bonos de rodamiento cargados el 2026-10-03 nacen marcados: es la regla del libro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employee_bonuses', function (Blueprint $table) {
            $table->boolean('prorate_by_worked_days')->default(false)->after('amount');
        });

        DB::table('hr_employee_bonuses')
            ->where('concept', 'Bono de rodamiento')
            ->update(['prorate_by_worked_days' => true]);
    }

    public function down(): void
    {
        Schema::table('hr_employee_bonuses', function (Blueprint $table) {
            $table->dropColumn('prorate_by_worked_days');
        });
    }
};
