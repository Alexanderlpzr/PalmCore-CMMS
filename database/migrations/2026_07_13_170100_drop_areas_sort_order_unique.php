<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 — `unique(plant_id, sort_order)` era una restricción disfrazada de orden.
 *
 * Se puso para «evitar empates que producirían orden no determinístico», pero un
 * empate en el orden de dos áreas no es un dato corrupto: es una preferencia sin
 * definir. Lo que sí producía era un error duro cada vez que el planificador
 * insertaba un área con el mismo número —y un test intermitente, porque la factory
 * chocaba consigo misma.
 *
 * El orden determinístico se consigue desempatando en la consulta (sort_order,
 * code), no prohibiendo que dos áreas compartan número. El índice
 * `areas_plant_sort_idx` ya existe y es el que sirve de verdad: acelera el listado
 * sin bloquear la escritura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropUnique('areas_plant_sort_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->unique(['plant_id', 'sort_order'], 'areas_plant_sort_order_unique');
        });
    }
};
