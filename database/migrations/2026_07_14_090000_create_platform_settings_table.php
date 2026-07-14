<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los ajustes de la plataforma: los que no son de ninguna empresa, sino del sistema.
 *
 * Van a la base y no a la caché a propósito. Un interruptor que decide si se hacen
 * copias de seguridad no puede olvidarse porque alguien reinició un contenedor o
 * limpió la caché: el olvido sería silencioso y solo se notaría el día que hace falta
 * un respaldo que nadie hizo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value')->nullable();

            // Quién lo cambió y cuándo: un ajuste de plataforma sin autor es una
            // decisión que nadie tomó.
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
