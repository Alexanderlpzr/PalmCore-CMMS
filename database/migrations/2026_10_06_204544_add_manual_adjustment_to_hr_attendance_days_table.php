<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horas de un día ajustadas a mano por talento humano.
 *
 * Las horas de un día salen de las marcas de la puerta. A veces hay que cambiarlas sin
 * tocar las marcas —una extra que no se autorizó, un turno que se paga distinto— y ese
 * ajuste tiene que decir quién lo hizo y por qué, y sobrevivir a la siguiente
 * reconstrucción: el día queda con `source = 'manual'` y el reloj ya no lo recalcula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_days', function (Blueprint $table) {
            $table->foreignUuid('adjusted_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            $table->timestampTz('adjusted_at', 0)->nullable()->after('adjusted_by');
            $table->text('adjustment_reason')->nullable()->after('adjusted_at');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by');
            $table->dropColumn(['adjusted_at', 'adjustment_reason']);
        });
    }
};
