<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los números que la ley cambia y la empresa no decide, con fecha de vigencia.
 *
 * La tentación es una tabla de ajustes: una fila por parámetro, RRHH edita el valor y
 * listo. Eso reescribe la historia. Si en marzo alguien sube el recargo dominical del
 * 80% al 90% y en abril se reabre la nómina de enero, enero tiene que seguir
 * liquidando al 80%: es lo que se firmó, lo que se aportó y lo que habría que defender
 * ante una demanda o una visita del Ministerio. Con un valor único y mutable, la
 * liquidación de enero cambia sola y en silencio.
 *
 * Por eso cada parámetro es una serie de tramos con `effective_from` / `effective_to`,
 * como un histórico de precios. Nunca se edita un tramo vigente: se cierra y se abre
 * otro. La liquidación de un período pregunta «qué regía en esa fecha», no «cuánto
 * vale hoy».
 *
 * Cada clave se versiona por separado y no en juegos completos. El SMLMV y el divisor
 * de jornada cambian por normas distintas y en fechas distintas —el primero cada enero,
 * el segundo con la reforma laboral—: atarlos al mismo juego obliga a reescribir doce
 * valores para corregir uno.
 *
 * `hr_payroll_concepts` es la tabla que más pleitos evita. El libro actual calcula
 * cuatro bases distintas y ninguna coincide con otra: el auxilio de transporte entra a
 * la base de prima pero no al IBC; las horas extras entran a prima pero no a la base de
 * vacaciones; la bonificación constitutiva entra al IBC y la no constitutiva no. Eso no
 * puede vivir cableado en PHP, porque cambia por convención, por pacto y por concepto
 * nuevo. Es una matriz, y la edita RRHH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_payroll_parameters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Clave del enum PayrollParameter. String y no enum de base de datos: añadir
            // un parámetro no debe requerir migración.
            $table->string('key', 60);

            // Seis decimales porque aquí conviven pesos (1750905.00), factores (0.35) y
            // horas del día (21.0 para el inicio de la jornada nocturna). Guardar la
            // ventana nocturna como hora decimal no es un atajo: es exactamente la forma
            // en que el clasificador la va a necesitar.
            $table->decimal('value', 16, 6);

            $table->date('effective_from');

            // null = vigente. Se cierra al abrir el tramo siguiente.
            $table->date('effective_to')->nullable();

            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(0);

            // Un solo tramo por clave y fecha de inicio. No impide solapes —eso lo valida
            // el servicio, que sí puede dar un mensaje legible— pero sí el duplicado
            // exacto, que es el error que se cuela al guardar dos veces.
            $table->unique(['tenant_id', 'key', 'effective_from']);
            $table->index(['tenant_id', 'key', 'effective_from', 'effective_to']);
        });

        Schema::create('hr_payroll_concepts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('code', 40);
            $table->string('name', 120);

            // 'devengado' | 'deduccion'.
            $table->string('type', 20);

            // La matriz. Cada columna responde «este concepto suma a esta base».
            $table->boolean('counts_ibc_health')->default(false);
            $table->boolean('counts_ibc_pension')->default(false);
            $table->boolean('counts_severance_base')->default(false);
            $table->boolean('counts_vacation_base')->default(false);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_concepts');
        Schema::dropIfExists('hr_payroll_parameters');
    }
};
