<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La entrada y la salida con que se ajustó un día a mano.
 *
 * El ajuste ya no se hace casilla por casilla: talento humano escribe la hora de entrada
 * y la de salida que se pagan, y las horas se clasifican solas con las mismas reglas del
 * reloj. Las marcas de la puerta no se tocan: siguen siendo la hora real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_days', function (Blueprint $table) {
            $table->timestampTz('adjusted_entry_at', 0)->nullable()->after('adjustment_reason');
            $table->timestampTz('adjusted_exit_at', 0)->nullable()->after('adjusted_entry_at');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_days', function (Blueprint $table) {
            $table->dropColumn(['adjusted_entry_at', 'adjusted_exit_at']);
        });
    }
};
