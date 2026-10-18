<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Dónde se usa»: el repuesto que lleva un equipo puede enlazarse con el del almacén.
 * Así la ficha del repuesto dice en qué equipos va y la del equipo, cuánto hay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_spare_parts', function (Blueprint $table) {
            $table->foreignUuid('spare_part_id')->nullable()->after('equipment_id')->constrained('spare_parts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_spare_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spare_part_id');
        });
    }
};
