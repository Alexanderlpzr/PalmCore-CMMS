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
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            // Sección de planta y Tipo II (causa concreta), como en la planilla del
            // cliente. Nullables: los paros ya existentes no los tienen.
            $table->string('section', 40)->nullable()->after('equipment_id');
            $table->string('stoppage_reason', 40)->nullable()->after('stoppage_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->dropColumn(['section', 'stoppage_reason']);
        });
    }
};
