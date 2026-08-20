<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El consumo eléctrico de la planta, en su propio dominio.
 *
 * La forma es la misma que la de los horómetros —lectura acumulada, delta contra la
 * anterior, reset cuando cambian el contador— porque el problema es el mismo. Pero la
 * tabla es propia y no `equipment_meter_readings`, y eso es deliberado: cinco
 * consumidores de aquella asumen que el delta son horas, y tres lo hacen **sin ninguna
 * guarda de unidad**. Un kWh escrito ahí se convertiría en horas de vida de los álabes
 * de la turbina y en horas trabajadas en un informe de gerencia.
 *
 * El argumento que zanja el debate no es técnico: la red pública no es un activo
 * mantenible. Es la acometida. Si el modelo de equipos no le sirve a uno de los tres
 * contadores, no es el modelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energy_meters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->constrained('plants')->cascadeOnDelete();

            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('source', 20); // EnergySource enum

            // Enlace de reporte, opcional y sin consecuencias: permite cruzar algún día
            // «kWh que generó la Planta 1250 kVA» con las horas de ese equipo. Nunca es
            // un camino por el que el kWh entre al mantenimiento. La red pública no
            // tiene equipo, y ese es justamente el caso que descarta modelar esto como
            // equipos con horómetro.
            $table->foreignUuid('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestampsTz(0);

            $table->unique(['plant_id', 'code']);
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('energy_meter_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('energy_meter_id')->constrained('energy_meters')->cascadeOnDelete();

            $table->date('reading_date');

            // Lo que marca el contador. Doce enteros: un contador de planta lleva
            // millones de kWh acumulados y sigue subiendo.
            $table->decimal('reading_value', 14, 1);

            // Lo que marcaba el anterior. Solo auditoría: es la única forma de explicar,
            // un año después, por qué el número bajó.
            $table->decimal('previous_value', 14, 1)->nullable();

            // El consumo del período. En un reset es la lectura del contador nuevo, no
            // la diferencia negativa.
            $table->decimal('delta', 14, 1)->default(0);

            $table->decimal('accumulated_value', 16, 1)->default(0);
            $table->boolean('is_reset')->default(false);

            $table->foreignUuid('recorded_by')->constrained('users');
            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            // Sin soft deletes: el histórico de lecturas es inmutable.

            // Un día tiene una lectura. La tabla de horómetros no tiene esta
            // restricción, y por eso dos lecturas del mismo día se guardan las dos y la
            // cuadrícula suma ambos deltas en silencio.
            $table->unique(['energy_meter_id', 'reading_date']);
            $table->index(['tenant_id', 'reading_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_meter_readings');
        Schema::dropIfExists('energy_meters');
    }
};
