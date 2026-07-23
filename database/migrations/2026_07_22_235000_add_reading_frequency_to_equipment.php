<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clasifica cada equipo como de Registro Diario o Semanal (o ninguno), tal como
 * el Excel separa las dos hojas de captura de horómetros. Nullable: un equipo sin
 * frecuencia no entra a ninguna ronda de lecturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('reading_frequency', 10)->nullable()->after('meter_unit');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('reading_frequency');
        });
    }
};
