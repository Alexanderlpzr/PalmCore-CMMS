<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID v7 — generado en PHP por HasUuids::newUniqueId()

            // tenant_id denormalizado — necesario para TenantScope sin JOIN a plants.
            // Debe mantenerse consistente con plant_id→plants.tenant_id (enforced en Policy/Action).
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->uuid('plant_id');
            $table->foreign('plant_id')
                ->references('id')
                ->on('plants')
                ->restrictOnDelete(); // Impide eliminar una planta con áreas hijas — protección CMMS

            // Identificación
            $table->string('code', 30);    // "EXT-01" — único por planta (ver UNIQUE abajo)
            $table->string('name', 255);   // "Área de Extracción"
            $table->text('description')->nullable();

            // Orden del flujo de proceso — usar múltiplos de 10 permite insertar entre valores.
            // Ejemplos: Recepción=10, Esterilización=20, Digestión=30, Prensado=40...
            // UNIQUE (plant_id, sort_order) impide empates que producirían orden no determinístico.
            $table->smallInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);

            // Código único dentro de la planta
            $table->unique(['plant_id', 'code'], 'areas_plant_code_unique');

            // Orden único dentro de la planta — sin empates en sort_order
            $table->unique(['plant_id', 'sort_order'], 'areas_plant_sort_order_unique');

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // TenantScope directo sin JOIN
        DB::statement('CREATE INDEX areas_tenant_id_idx ON areas (tenant_id)');

        // Listado ordenado de áreas en dashboards, reportes y selectores — query más frecuente
        DB::statement('CREATE INDEX areas_plant_sort_idx ON areas (plant_id, sort_order)');

        // Áreas activas de una planta — frecuente en todos los formularios de selección de área
        DB::statement('CREATE INDEX areas_plant_active_idx ON areas (plant_id, is_active)');
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
