<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de repuestos de cada equipo: qué repuesto lleva, para pedirlo sin
 * adivinar. Es solo un listado.
 *
 * No es equipment_components (piezas con horas trabajadas y vida útil, que
 * disparan preventivos) ni spare_parts (inventario del almacén, con stock
 * mínimo y punto de reorden). Aquí no hay existencias ni mantenimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_spare_parts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('part_number', 100)->nullable();
            $table->text('notes')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_spare_parts');
    }
};
