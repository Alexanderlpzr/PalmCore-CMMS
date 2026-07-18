<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo RCM-lite: para cada equipo (o pieza puntual), qué modos de falla
 * puede tener y qué consecuencia trae cada uno (seguridad/ambiental,
 * operacional, no-operacional, oculta). Cuando la consecuencia es oculta,
 * failure_finding_plan_id enlaza la tarea periódica que la revela — sin
 * esa tarea, una falla oculta se acumula en silencio hasta combinarse con
 * una segunda falla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failure_mode_analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            // Nullable a propósito: null = aplica a todo el equipo, igual que
            // maintenance_plans.equipment_component_id.
            $table->foreignUuid('equipment_component_id')->nullable()
                ->constrained('equipment_components')
                ->nullOnDelete();

            $table->string('failure_mode', 40); // FailureMode enum
            $table->string('consequence_category', 20); // FailureConsequenceCategory enum
            $table->text('effect_description')->nullable();

            $table->foreignUuid('failure_finding_plan_id')->nullable()
                ->constrained('maintenance_plans')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index(['tenant_id', 'equipment_id', 'consequence_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failure_mode_analyses');
    }
};
