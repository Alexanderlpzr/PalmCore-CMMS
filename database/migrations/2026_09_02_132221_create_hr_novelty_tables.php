<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los días que no fueron días trabajados, y los descuentos que se repiten cada mes.
 *
 * Las novedades cierran el mejor control que tiene el libro de Excel: allí, en los 48
 * empleados sin una sola excepción, los días laborados más las novedades dan exactamente
 * 30. Ese cuadre solo es posible si las ausencias, incapacidades, permisos, calamidades y
 * vacaciones viven en algún lado; hasta ahora el sistema solo sabía de las marcas del
 * reloj, que dicen quién estuvo pero no por qué faltó el resto.
 *
 * Se guardan por rango de fechas y no por cantidad de días, que es como están en el
 * Excel. La diferencia importa: «seis días de vacaciones» no dice cuáles, y sin saber
 * cuáles no se puede comprobar que no se solapan con un turno que el reloj registró, ni
 * repartir una incapacidad que cruza dos meses. Los días se derivan del rango.
 *
 * `hr_employee_deductions` es la otra mitad de lo que hoy se escribe a mano: en la nómina
 * de agosto hay descuentos de seguro funerario y de póliza cargados empleado por empleado,
 * como números sueltos. Aquí se declaran una vez y se aplican solos mientras estén
 * vigentes, que es lo mismo que hacen los parámetros con las tarifas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_novelties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // NoveltyType.
            $table->string('type', 40);

            $table->date('starts_on');
            $table->date('ends_on');

            // Soporte: la incapacidad tiene número, las vacaciones tienen acta.
            $table->string('reference', 60)->nullable();
            $table->text('notes')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            $table->index(['tenant_id', 'employee_id', 'starts_on', 'ends_on']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('hr_employee_bonuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // BonusType: vivienda, constitutiva, no constitutiva. La distinción no es de
            // nombre: la constitutiva entra al IBC y la no constitutiva no, y ahí se
            // juegan los aportes de 35 de los 48 trabajadores.
            $table->string('type', 30);

            $table->string('concept', 80);
            $table->decimal('amount', 14, 2);

            // El mismo mecanismo sirve para la bonificación de un solo mes —del 1 al 31 de
            // agosto— y para la que se repite. En el libro actual estas cifras están
            // pegadas a mano, con decimales largos y sin fórmula que las respalde: 21
            // millones, el segundo componente del devengado, imposibles de auditar.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            $table->index(['tenant_id', 'employee_id', 'effective_from', 'effective_to']);
        });

        Schema::create('hr_employee_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            $table->string('concept', 80);
            $table->decimal('amount', 14, 2);

            // Mismo patrón que los parámetros: un descuento que terminó no se borra, se
            // cierra. Reabrir la nómina de un mes pasado tiene que volver a aplicarlo.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            $table->index(['tenant_id', 'employee_id', 'effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_deductions');
        Schema::dropIfExists('hr_employee_bonuses');
        Schema::dropIfExists('hr_employee_novelties');
    }
};
