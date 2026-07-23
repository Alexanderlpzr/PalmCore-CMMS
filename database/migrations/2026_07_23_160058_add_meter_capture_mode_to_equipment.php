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
        Schema::table('equipment', function (Blueprint $table) {
            // Por defecto 'accumulated' para no reinterpretar los equipos existentes
            // (ni la lógica de dial que ya prueban muchos tests). Los equipos nuevos
            // arrancan en 'daily_hours' desde el formulario, que es como captura la planta.
            $table->string('meter_capture_mode', 20)->default('accumulated')->after('reading_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('meter_capture_mode');
        });
    }
};
