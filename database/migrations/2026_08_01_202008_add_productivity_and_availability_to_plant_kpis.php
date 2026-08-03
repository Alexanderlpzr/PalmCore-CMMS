<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los tres indicadores mensuales de planta, con las fórmulas que El Pajuil usa.
 *
 * Con las cifras de referencia de la planta (HP 452 h, HASEO 8 h, HPREN 420 h,
 * HMTTO 14 h, HOPER 10 h, FP 6.000 t):
 *
 *     Eficiencia     = HPREN / (HP − HASEO)       = 420 / 444  = 94,59 %
 *     Productividad  = FP    / (HP − HASEO)       = 6000 / 444 = 13,51 t/h
 *     Disponibilidad = (HP − HASEO − HMTTO) / HP  = 430 / 452  = 95,13 %
 *
 * Dos cosas cambian respecto a lo que había:
 *
 * 1. La eficiencia deja de dividir por las horas pagadas y pasa a dividir por
 *    las horas que la planta *podía* prensar. El aseo es tiempo que nunca iba a
 *    producir; cobrárselo a la planta la castigaba por hacer mantenimiento
 *    preventivo. Con las cifras de arriba el indicador pasa de 92,92 % a 94,59 %.
 *    El histórico se recalcula solo, porque la columna es GENERATED.
 *
 * 2. Entra la tonelada. `production_calendar` ya era el único sitio donde el
 *    planificador declara el día; ahora declara también cuánta fruta entró, y el
 *    cierre mensual la totaliza. `processed_tons_is_manual` marca los meses en
 *    que alguien corrigió el total a mano (típicamente porque báscula y
 *    laboratorio no coinciden), para que el cierre siguiente no lo pise.
 *
 * La disponibilidad se guarda simplificada. HASEO + HMTTO es exactamente
 * `maintenance_lost_hours` —las horas que mantenimiento le quitó a la planta,
 * programadas o no— así que (HP − HASEO − HMTTO) / HP se reduce a
 * (HP − maintenance_lost_hours) / HP. Escrita así además sigue siendo correcta
 * cuando dos paros se solapan: esas horas se pierden una sola vez, y
 * `maintenance_lost_hours` ya es la unión de los intervalos, no su suma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_calendar', function (Blueprint $table): void {
            // Toneladas de RFF que entraron ese día. Cero es legítimo, igual que
            // en programmed_hours: un día sin molienda no es un dato faltante.
            $table->decimal('processed_tons', 10, 2)->default(0)->after('programmed_hours');
        });

        Schema::table('plant_monthly_kpis', function (Blueprint $table): void {
            // HASEO — el subconjunto planificado de maintenance_lost_hours.
            $table->decimal('cleaning_hours', 8, 2)->default(0)->after('maintenance_lost_hours');
            $table->decimal('processed_tons', 12, 2)->default(0)->after('cleaning_hours');
            $table->boolean('processed_tons_is_manual')->default(false)->after('processed_tons');
        });

        // La eficiencia ya existía como columna generada: hay que soltarla para
        // cambiarle la fórmula, no se puede alterar en sitio.
        DB::statement('ALTER TABLE plant_monthly_kpis DROP COLUMN efficiency_percentage');

        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN efficiency_percentage NUMERIC(5,2)
            GENERATED ALWAYS AS (
                CASE WHEN (programmed_hours - cleaning_hours) > 0
                     THEN ROUND((effective_hours / (programmed_hours - cleaning_hours)) * 100, 2)
                     ELSE NULL
                END
            ) STORED
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN productivity_tons_per_hour NUMERIC(10,2)
            GENERATED ALWAYS AS (
                CASE WHEN (programmed_hours - cleaning_hours) > 0
                     THEN ROUND(processed_tons / (programmed_hours - cleaning_hours), 2)
                     ELSE NULL
                END
            ) STORED
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN availability_percentage NUMERIC(5,2)
            GENERATED ALWAYS AS (
                CASE WHEN programmed_hours > 0
                     THEN ROUND(((programmed_hours - maintenance_lost_hours) / programmed_hours) * 100, 2)
                     ELSE NULL
                END
            ) STORED
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plant_monthly_kpis DROP COLUMN availability_percentage');
        DB::statement('ALTER TABLE plant_monthly_kpis DROP COLUMN productivity_tons_per_hour');
        DB::statement('ALTER TABLE plant_monthly_kpis DROP COLUMN efficiency_percentage');

        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN efficiency_percentage NUMERIC(5,2)
            GENERATED ALWAYS AS (
                CASE WHEN programmed_hours > 0
                     THEN ROUND((effective_hours / programmed_hours) * 100, 2)
                     ELSE NULL
                END
            ) STORED
        SQL);

        Schema::table('plant_monthly_kpis', function (Blueprint $table): void {
            $table->dropColumn(['cleaning_hours', 'processed_tons', 'processed_tons_is_manual']);
        });

        Schema::table('production_calendar', function (Blueprint $table): void {
            $table->dropColumn('processed_tons');
        });
    }
};
