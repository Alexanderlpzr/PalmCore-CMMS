<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El trabajador pasa a tener dos códigos, porque en El Pajuil son dos cosas distintas.
 *
 * `employee_code` es el consecutivo corto de la hoja de personal —001, 004, 017— con el
 * que se nombra a la gente en planta. `company_code` es el «código empresarial» que ya
 * venía en el libro (O4092021, 220820231), donde van pegadas la fecha de vinculación y un
 * correlativo.
 *
 * Hasta hoy el consecutivo no existía en el sistema y `employee_code` guardaba el
 * empresarial. Esta migración lo mueve a su columna nueva y deja el consecutivo vacío,
 * que se carga después con `hr:import-roster` desde la columna CODIGO de la hoja.
 *
 * Ninguno de los dos es único a propósito: en el libro el empresarial 220820231 está
 * repetido en dos personas. La llave del trabajador sigue siendo su documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('company_code', 30)->nullable()->after('employee_code');
        });

        DB::table('hr_employees')->update(['company_code' => DB::raw('employee_code')]);
        DB::table('hr_employees')->update(['employee_code' => null]);
    }

    public function down(): void
    {
        // El consecutivo se pierde al volver atrás: antes no existía, y la columna que
        // queda solo tiene sitio para uno de los dos códigos.
        DB::table('hr_employees')->update(['employee_code' => DB::raw('company_code')]);

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn('company_code');
        });
    }
};
