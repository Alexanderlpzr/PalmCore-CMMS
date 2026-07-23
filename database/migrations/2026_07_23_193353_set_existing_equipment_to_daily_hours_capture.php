<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La planta captura horas trabajadas por día, no lecturas de dial acumulado.
     * Los equipos ya existentes quedaron en 'accumulated' (default de la columna al
     * crearla); se pasan todos a 'daily_hours'. Los equipos nuevos ya nacen así desde
     * el formulario. Backfill de datos, sin lógica de dominio (por eso DB::table, sin
     * el modelo ni sus scopes de tenant).
     */
    public function up(): void
    {
        DB::table('equipment')->update(['meter_capture_mode' => 'daily_hours']);
    }

    public function down(): void
    {
        // Backfill de datos: no se revierte (no se guarda el modo previo de cada equipo).
    }
};
