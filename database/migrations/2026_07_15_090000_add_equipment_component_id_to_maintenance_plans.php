<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un plan preventivo puede apuntar a una pieza concreta, no solo al equipo entero.
 *
 * «Cambio de aceite de la unidad de potencia cada 500 h» no es lo mismo que «revisión
 * general de la prensa cada 500 h»: el primero es un componente adentro del segundo.
 * El horómetro sigue siendo el del equipo —los componentes no tienen uno propio,
 * comparten el que ya se lee cada día— así que todo el motor de vencimientos
 * (MaintenanceSchedule, PreventiveWorkOrderGenerator) sigue funcionando exactamente
 * igual. Solo hace falta decir a cuál pieza pertenece el plan.
 *
 * Nullable a propósito: un plan sin componente sigue siendo un plan de equipo, el
 * comportamiento de siempre. nullOnDelete en vez de cascade — si el componente físico
 * se borra del catálogo, el plan no debería desaparecer con él; el historial de
 * mantenimiento es un hecho que sobrevive a que alguien reorganice el inventario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->foreignUuid('equipment_component_id')->nullable()
                ->after('equipment_id')
                ->constrained('equipment_components')
                ->nullOnDelete();

            $table->index(['equipment_component_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_component_id');
        });
    }
};
