<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El «Tipo I» del cliente, guardado sin traducir.
 *
 * Su Tipo I dice quién paró la línea; nuestro `stoppage_category` dice qué se
 * rompió. Hasta hoy solo cabía uno, y eso obligaba a elegir entre reproducir su
 * informe o decir la verdad sobre el MTBF. Caben los dos.
 *
 * Nullable a propósito: un paro registrado en Fronda por un supervisor que nunca
 * oyó hablar del Tipo I no tiene por qué inventarse uno. El servicio deduce el
 * valor probable a partir de la causa física, pero el dato que viene de la planilla
 * se guarda tal como ellos lo escribieron — contradicciones incluidas, porque esas
 * contradicciones son el hallazgo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->string('reported_type', 20)->nullable()->after('stoppage_cause');

            // El informe mensual del cliente: horas por Tipo I en una ventana.
            $table->index(['plant_id', 'reported_type']);
        });
    }

    public function down(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->dropIndex(['plant_id', 'reported_type']);
            $table->dropColumn('reported_type');
        });
    }
};
