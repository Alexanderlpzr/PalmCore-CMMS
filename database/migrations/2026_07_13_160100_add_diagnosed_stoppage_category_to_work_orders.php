<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A4 — el Tipo I que solo se sabe después de abrir la máquina.
 *
 * Una OT correctiva nace sin saber qué se rompió, así que el paro que abre queda
 * en «otro». El diagnóstico llega horas después, cuando el técnico ya destapó el
 * reductor: ahí es donde se sabe si fue mecánico, eléctrico o de instrumentación.
 *
 * El campo vive en la OT —no solo en el paro— porque el diagnóstico es un hecho de
 * la orden: existe aunque la máquina nunca se haya detenido (falla sin paro) y
 * sobrevive a cualquier corrección posterior del paro. De la OT se propaga al paro
 * al completarla; ese es el único momento en que el dato existe de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->string('diagnosed_stoppage_category', 20)->nullable()->after('failure_mode');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('diagnosed_stoppage_category');
        });
    }
};
