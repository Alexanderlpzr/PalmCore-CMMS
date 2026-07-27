<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Tercer rubro del desglose de costo al cerrar la OT (junto a mano de
            // obra y repuestos): lubricantes, filtros, empaques — lo que se
            // consume pero no es ni mano de obra ni un repuesto propiamente.
            $table->decimal('actual_cost_consumables', 12, 2)->nullable()->after('actual_cost_parts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('actual_cost_consumables');
        });
    }
};
