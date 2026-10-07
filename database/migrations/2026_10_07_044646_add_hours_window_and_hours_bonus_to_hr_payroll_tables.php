<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La nómina de un mes paga el sueldo del mes y las horas de otra ventana.
 *
 * La extractora corta las horas el 26: la nómina de octubre lleva el sueldo de octubre y
 * las horas del 27 de septiembre al 26 de octubre, para revisarlas antes de pagar.
 * `hours_from` / `hours_to` son esa ventana; vacías, las horas son las del período.
 *
 * Y lo que la regla del bono saca de las horas extras —los días del mes anterior y lo que
 * pasa del tope diario— se paga como bonificación constitutiva: `hours_bonus_total`, con
 * su detalle por clase de hora en `hours_bonus_breakdown`, igual que la hoja
 * BONIFICACIONES del formato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_payroll_runs', function (Blueprint $table) {
            $table->date('hours_from')->nullable()->after('period_end');
            $table->date('hours_to')->nullable()->after('hours_from');
        });

        Schema::table('hr_payroll_entries', function (Blueprint $table) {
            $table->decimal('hours_bonus_total', 14, 2)->default(0)->after('surcharges_total');
            $table->json('hours_bonus_breakdown')->nullable()->after('hours_bonus_total');
        });
    }

    public function down(): void
    {
        Schema::table('hr_payroll_entries', function (Blueprint $table) {
            $table->dropColumn(['hours_bonus_total', 'hours_bonus_breakdown']);
        });

        Schema::table('hr_payroll_runs', function (Blueprint $table) {
            $table->dropColumn(['hours_from', 'hours_to']);
        });
    }
};
