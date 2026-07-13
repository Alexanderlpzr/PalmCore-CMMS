<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A1 — el costo real de mantenimiento incluye a quien no es empleado.
 *
 * Disam, AIC, Servimontajes, Montajes Industriales HF: la planta ya trabaja con
 * ellos. Aparecen como «Ejecutante» en la hoja de vida y como «Responsable» en la
 * programación diaria — pero en Fronda no existen, porque asignar trabajo exige un
 * `User`. Resultado: el programa impreso desde Fronda sale con filas de menos, y
 * el costo de una OT ejecutada por un tercero es cero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('name', 150);
            $table->string('tax_id', 30)->nullable();          // NIT
            $table->string('specialty', 60)->nullable();       // mecánico, montajes, eléctrico…
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->decimal('hourly_rate', 12, 2)->nullable(); // tarifa de referencia, no la pactada
            $table->string('currency_code', 3)->default('COP');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            // Dos contratistas con el mismo nombre en la misma empresa son un error
            // de digitación, no dos empresas.
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('work_order_contractors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUuid('contractor_id')->constrained('contractors');

            $table->text('scope')->nullable();                   // qué se contrató
            // Lo pactado para *esta* OT, congelado: cambiar la tarifa del contratista
            // mañana no puede reescribir lo que costó un trabajo de junio.
            $table->decimal('agreed_cost', 14, 2)->nullable();
            $table->string('currency_code', 3)->default('COP');
            $table->string('invoice_number', 60)->nullable();
            $table->text('notes')->nullable();

            $table->timestampsTz(0);

            $table->unique(['work_order_id', 'contractor_id']);
            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_contractors');
        Schema::dropIfExists('contractors');
    }
};
