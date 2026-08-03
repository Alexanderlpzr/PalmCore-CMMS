<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira `equipment_production_logs`, que en producción existe sin dueño.
 *
 * Era el almacén de la vertical OEE (disponibilidad × rendimiento × calidad por
 * equipo y turno). La auditoría de higiene del 2026-07-29 la dio de baja porque
 * nada le escribía nunca —ni factory, ni seeder, ni formulario— y porque la
 * planta no mide OEE: mide eficiencia, productividad y disponibilidad de planta,
 * que es lo que ahora calcula PlantKpiService. Se borraron el modelo, el
 * servicio y la migración que la creaba, pero la tabla ya estaba aplicada en el
 * VPS y allí se quedó.
 *
 * El resultado era una divergencia silenciosa: una instalación nueva no tiene
 * esta tabla y producción sí, así que cualquier consulta al esquema decía cosas
 * distintas según dónde se corriera. Se confirmó vacía (0 filas) antes de esto.
 *
 * `down()` la reconstruye tal como estaba en el VPS —columnas, índices y claves
 * incluidas— para que la migración siga siendo reversible, aunque nada en el
 * código actual la use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('equipment_production_logs');
    }

    public function down(): void
    {
        Schema::create('equipment_production_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            $table->date('log_date');
            $table->string('shift', 20)->nullable();

            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('downtime_minutes')->default(0);
            $table->decimal('ideal_rate_per_hour', 10, 2)->nullable();
            $table->decimal('total_units', 12, 2)->default(0);
            $table->decimal('good_units', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['tenant_id', 'log_date']);
        });
    }
};
