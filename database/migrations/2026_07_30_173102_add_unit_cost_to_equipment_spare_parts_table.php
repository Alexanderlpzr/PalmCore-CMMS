<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costo del repuesto, para saber cuánto vale reponerlo y qué suma la lista
 * completa del equipo. Misma precisión que equipment_components.unit_cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_spare_parts', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->nullable()->unsigned()->after('part_number');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_spare_parts', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
