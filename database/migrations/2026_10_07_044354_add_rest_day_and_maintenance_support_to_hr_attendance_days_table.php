<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos marcas del día que el reloj no puede deducir y talento humano sí sabe.
 *
 * `rest_day_worked`: el domingo o festivo que alguien de mantenimiento viene a trabajar
 * en su día de descanso. En el formato de horas extras de la extractora ese día va
 * entero como extra dominical, sin jornada ordinaria y sin el tope de dos horas; el
 * reloj, en cambio, le daría siete horas de jornada con recargo. Marcado, el día se
 * clasifica todo como extra.
 *
 * `maintenance_support`: el operario de producción que ese día trabajó para
 * mantenimiento. No cambia lo que se le paga; cambia a qué grupo se le cargan sus horas
 * en el indicador «factor de horas» («Apoyo a mantenimiento»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_days', function (Blueprint $table) {
            $table->boolean('rest_day_worked')->default(false)->after('worked_hours');
            $table->boolean('maintenance_support')->default(false)->after('rest_day_worked');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_days', function (Blueprint $table) {
            $table->dropColumn(['rest_day_worked', 'maintenance_support']);
        });
    }
};
