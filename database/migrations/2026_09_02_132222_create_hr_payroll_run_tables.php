<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La nómina de un período y el renglón de cada trabajador dentro de ella.
 *
 * El renglón es ancho —cuarenta y tantas columnas— y eso es deliberado. La tentación es
 * guardar solo el neto y recalcular el resto cuando alguien pregunte, pero un
 * desprendible que se reconstruye no es un comprobante: es una opinión de hoy sobre lo
 * que se pagó entonces. Aquí cada cifra que aparece impresa está guardada, incluidos el
 * nombre, el cargo y el salario del trabajador en ese momento, porque los tres cambian y
 * el desprendible de agosto tiene que seguir diciendo lo que decía en agosto.
 *
 * `parameters_snapshot` guarda los diecinueve parámetros que se usaron. Con las vigencias
 * ya se puede saber qué regía en una fecha, pero esto responde algo distinto y más
 * directo: con qué números se calculó **este** renglón. Si alguna vez las dos respuestas
 * no coinciden, hay un problema que conviene poder ver.
 *
 * Las horas se guardan junto a su valor y no solo el valor: es lo que hace posible cuadrar
 * contra el Excel columna por columna, que es como se va a validar este módulo antes de
 * reemplazarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name', 120);

            // El período que se liquida. Son fechas y no «mes 8» porque una nómina
            // quincenal o una liquidación parcial no caben en un número de mes.
            $table->date('period_start');
            $table->date('period_end');

            // PayrollRunStatus.
            $table->string('status', 20)->default('borrador');

            $table->timestampTz('calculated_at', 0)->nullable();
            $table->timestampTz('closed_at', 0)->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();

            // Totales del período, para no sumar 48 renglones cada vez que se pinta la lista.
            $table->decimal('total_earned', 16, 2)->default(0);
            $table->decimal('total_deducted', 16, 2)->default(0);
            $table->decimal('total_net', 16, 2)->default(0);
            $table->unsignedSmallInteger('employee_count')->default(0);

            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'period_start', 'period_end']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('hr_payroll_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('payroll_run_id')->constrained('hr_payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // Foto del trabajador en el momento de liquidar. El nombre, el cargo y el
            // salario cambian; el desprendible ya impreso, no.
            $table->string('employee_name', 180);
            $table->string('document_number', 30);
            $table->string('position', 120)->nullable();
            $table->decimal('base_salary', 14, 2);

            $table->decimal('day_value', 14, 4);
            $table->decimal('hour_value', 14, 4);

            $table->decimal('worked_days', 6, 2)->default(0);
            $table->decimal('novelty_days', 6, 2)->default(0);
            $table->decimal('total_days', 6, 2)->default(0);

            // Las siete bolsas, cada una con sus horas y su valor. El factor aplicado vive
            // en `parameters_snapshot`.
            foreach ([
                'night_surcharge',
                'sunday_surcharge',
                'night_sunday_surcharge',
                'overtime_day',
                'overtime_night',
                'overtime_sunday_day',
                'overtime_sunday_night',
            ] as $bucket) {
                $table->decimal("{$bucket}_hours", 8, 4)->default(0);
                $table->decimal("{$bucket}_amount", 14, 2)->default(0);
            }

            $table->decimal('surcharges_total', 14, 2)->default(0);

            // Novedades, ya valoradas y desglosadas para el desprendible.
            $table->json('novelty_breakdown')->nullable();
            $table->decimal('absence_deduction', 14, 2)->default(0);
            $table->decimal('paid_novelties_amount', 14, 2)->default(0);
            $table->decimal('vacation_amount', 14, 2)->default(0);

            $table->decimal('basic_earned', 14, 2)->default(0);
            $table->decimal('earned_with_surcharges', 14, 2)->default(0);

            $table->decimal('bonus_housing', 14, 2)->default(0);
            $table->decimal('bonus_constitutive', 14, 2)->default(0);
            $table->decimal('bonus_non_constitutive', 14, 2)->default(0);
            $table->decimal('bonuses_total', 14, 2)->default(0);

            $table->decimal('transport_allowance', 14, 2)->default(0);
            $table->decimal('total_earned', 14, 2)->default(0);

            // Las cuatro bases. Ninguna coincide con otra, y eso es lo correcto.
            $table->decimal('ibc_health', 14, 2)->default(0);
            $table->decimal('ibc_pension', 14, 2)->default(0);
            $table->decimal('severance_base', 14, 2)->default(0);
            $table->decimal('vacation_base', 14, 2)->default(0);

            $table->decimal('health_deduction', 14, 2)->default(0);
            $table->decimal('pension_deduction', 14, 2)->default(0);
            $table->decimal('solidarity_fund', 14, 2)->default(0);
            $table->decimal('withholding_tax', 14, 2)->default(0);
            $table->json('other_deductions_breakdown')->nullable();
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('total_deducted', 14, 2)->default(0);

            $table->decimal('net_pay', 14, 2)->default(0);

            $table->json('parameters_snapshot')->nullable();

            // Lo que hay que mirar antes de pagar: días que no cuadran a 30, horas sin
            // confirmar, parámetros incoherentes.
            $table->json('warnings')->nullable();

            $table->timestampsTz(0);

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['tenant_id', 'payroll_run_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_entries');
        Schema::dropIfExists('hr_payroll_runs');
    }
};
