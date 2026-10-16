<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El fin del horario laboral de un turno, tal como lo trae el libro de horas extras
 * (columna «FIN HORARIO LABORAL»). Va en la marca de entrada que carga el libro: lo
 * ordinario de ese día llega hasta ahí y solo lo que pasa es extra. Sin él, el día se
 * cuenta con la jornada del parámetro (7 h).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_scans', function (Blueprint $table) {
            $table->timestampTz('scheduled_end_at', 0)->nullable()->after('scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_scans', function (Blueprint $table) {
            $table->dropColumn('scheduled_end_at');
        });
    }
};
