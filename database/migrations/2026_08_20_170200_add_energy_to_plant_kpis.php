<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El consumo eléctrico entra al cierre mensual de planta.
 *
 * Vive aquí y no en una tabla propia porque su denominador ya está en esta fila:
 * `processed_tons` —la fruta del mes— es lo mismo que divide a la productividad en t/h.
 * La hoja de la planta pone RFF/MES en la primera línea del informe de energía por esa
 * misma razón.
 *
 *     KWh TOTAL      = red + planta eléctrica + turbina
 *     KWh/RFF        = KWh TOTAL / fruta procesada     ← el indicador que va a gerencia
 *     ENERGÍA LIMPIA = turbina / KWh TOTAL
 *
 * Los tres son GENERATED, como los otros tres indicadores de la tabla: el número que ve
 * gerencia no puede separarse de los kWh con que se calculó.
 *
 * Las tres fuentes son **nullable**, y eso importa. En 2025 hay cinco meses sin dato de
 * turbina, y cero kWh de turbina no es lo mismo que no saber cuántos fueron: lo primero
 * dice que la planta funcionó a diésel, lo segundo que nadie lo anotó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plant_monthly_kpis', function (Blueprint $table) {
            $table->decimal('kwh_grid', 12, 1)->nullable()->after('processed_tons_is_manual');
            $table->decimal('kwh_genset', 12, 1)->nullable()->after('kwh_grid');
            $table->decimal('kwh_turbine', 12, 1)->nullable()->after('kwh_genset');

            // Marca el mes que vino de la hoja histórica. El cierre mensual no lo pisa:
            // recalcular sobre lecturas diarias que nunca existieron lo borraría.
            $table->boolean('energy_is_imported')->default(false)->after('kwh_turbine');
        });

        // COALESCE y no suma directa: en Postgres, NULL + 3 es NULL, y un mes sin dato de
        // turbina dejaría sin total un mes del que sí sabemos la red y el diésel.
        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN kwh_total NUMERIC(12,1)
            GENERATED ALWAYS AS (
                CASE WHEN kwh_grid IS NULL AND kwh_genset IS NULL AND kwh_turbine IS NULL
                     THEN NULL
                     ELSE COALESCE(kwh_grid, 0) + COALESCE(kwh_genset, 0) + COALESCE(kwh_turbine, 0)
                END
            ) STORED
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN kwh_per_ton NUMERIC(10,2)
            GENERATED ALWAYS AS (
                CASE WHEN processed_tons > 0
                      AND NOT (kwh_grid IS NULL AND kwh_genset IS NULL AND kwh_turbine IS NULL)
                     THEN ROUND(
                         (COALESCE(kwh_grid, 0) + COALESCE(kwh_genset, 0) + COALESCE(kwh_turbine, 0))
                         / processed_tons, 2)
                     ELSE NULL
                END
            ) STORED
        SQL);

        // Sin dato de turbina no hay porcentaje de energía limpia: dar 0 % afirmaría que
        // la turbina no generó nada, que es justo lo que no sabemos.
        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN clean_energy_percentage NUMERIC(5,2)
            GENERATED ALWAYS AS (
                CASE WHEN kwh_turbine IS NOT NULL
                      AND (COALESCE(kwh_grid, 0) + COALESCE(kwh_genset, 0) + kwh_turbine) > 0
                     THEN ROUND(
                         (kwh_turbine
                          / (COALESCE(kwh_grid, 0) + COALESCE(kwh_genset, 0) + kwh_turbine)) * 100, 2)
                     ELSE NULL
                END
            ) STORED
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plant_monthly_kpis DROP COLUMN IF EXISTS clean_energy_percentage');
        DB::statement('ALTER TABLE plant_monthly_kpis DROP COLUMN IF EXISTS kwh_per_ton');
        DB::statement('ALTER TABLE plant_monthly_kpis DROP COLUMN IF EXISTS kwh_total');

        Schema::table('plant_monthly_kpis', function (Blueprint $table) {
            $table->dropColumn(['kwh_grid', 'kwh_genset', 'kwh_turbine', 'energy_is_imported']);
        });
    }
};
