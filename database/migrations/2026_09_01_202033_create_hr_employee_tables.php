<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El maestro de personal, su carné QR y el registro crudo de portería.
 *
 * Un empleado no es un `User`. De los 48 de la extractora, ninguno inicia sesión: el
 * operario de prensa existe para la nómina, no para el CMMS. Colgar la nómina de
 * `users` obligaría a crear 48 cuentas que nadie usa, con contraseña que nadie rota,
 * y a mezclar la tabla más sensible del sistema con la de autenticación. `user_id`
 * queda opcional para los pocos que sí entran —el supervisor que además reporta OTs—
 * y ese enlace no es la identidad del empleado, solo una comodidad.
 *
 * `excluded_from_overtime` es la columna que evita el error más caro del módulo. En la
 * nómina de agosto, 14 de los 48 tienen exactamente cero horas extras y cero recargos:
 * supervisores, coordinadores, Jefe de Mantenimiento y el Director de Planta. No es
 * casualidad ni omisión, son trabajadores de dirección, confianza y manejo, que por ley
 * no causan horas extras. Cuando portería les escanee la salida a las 21:00 —y se la va
 * a escanear— el clasificador tiene que saber que esas horas no se pagan. Sin esta
 * bandera, el reloj genera pasivo laboral por sí solo.
 *
 * Los escaneos son inmutables y sin `deleted_at`: son la prueba de a qué hora entró
 * alguien a la planta. Se corrigen con un escaneo manual que deja rastro, nunca
 * borrando el anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->nullable()->constrained('plants')->nullOnDelete();

            // Opcional y sin consecuencias: el empleado existe sin cuenta.
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('document_type', 10)->default('CC');
            $table->string('document_number', 30);

            $table->string('first_name', 80);
            $table->string('last_name', 80);

            // Cargo como texto y no como catálogo: los 21 cargos del libro actual no
            // gobiernan ningún cálculo. El día que lo hagan —escalas salariales, dotación
            // por cargo— se normaliza; hoy sería una tabla que solo se lee.
            $table->string('position', 120)->nullable();

            $table->decimal('base_salary', 14, 2)->default(0);

            // 'ordinario' | 'integral'. El salario integral no causa horas extras ni
            // recargos y su IBC es el 70%: son dos reglas distintas, no un matiz.
            $table->string('salary_type', 20)->default('ordinario');

            // Dirección, confianza y manejo. Ver la nota de cabecera.
            $table->boolean('excluded_from_overtime')->default(false);

            // null = decide la regla (salario <= N SMLMV). true/false = lo forzó RRHH,
            // que es lo que hoy en el Excel se hace borrando la fórmula de la celda y
            // dejando el hueco sin explicación.
            $table->boolean('transport_allowance_override')->nullable();

            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();

            // EmploymentStatus enum.
            $table->string('status', 20)->default('activo');

            // Seguridad social — hoy solo se guardan; los necesita PILA el día que exista.
            $table->string('eps', 120)->nullable();
            $table->string('pension_fund', 120)->nullable();
            $table->string('severance_fund', 120)->nullable();
            $table->string('arl_risk_class', 5)->nullable();

            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz();

            // La cédula identifica a la persona dentro de la empresa. Es la llave que el
            // libro de Excel debió usar y no usó: allí el cruce entre hojas se hace por
            // nombre en texto, y un acento corregido de un solo lado deja al empleado
            // con cero horas sin que nada proteste.
            $table->unique(['tenant_id', 'document_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'plant_id']);
        });

        Schema::create('hr_employee_qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();

            // UUID v4 (aleatorio) y no v7 (ordenado en el tiempo), igual que el QR de
            // equipos: un token predecible deja marcar la entrada de otro.
            $table->uuid('qr_token')->unique();
            $table->string('qr_image_path', 255)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestampTz('generated_at', 0)->nullable();
            $table->timestampTz('last_scanned_at', 0)->nullable();
            $table->unsignedInteger('scan_count')->default(0);

            $table->timestampsTz(0);
            $table->softDeletesTz();

            $table->index(['tenant_id', 'employee_id']);
        });

        Schema::create('hr_attendance_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignUuid('employee_qr_code_id')->nullable()->constrained('hr_employee_qr_codes')->nullOnDelete();

            $table->timestampTz('scanned_at', 0);

            // 'entrada' | 'salida'. Se deduce del último escaneo del día y se guarda ya
            // resuelto: dentro de un año nadie va a poder recalcular qué se dedujo
            // entonces si solo quedan las marcas sueltas.
            $table->string('direction', 10);

            // 'qr' | 'manual'. El manual es la corrección con rastro; existe porque el
            // celular se queda sin batería y la planta no se detiene por eso.
            $table->string('source', 10)->default('qr');

            // Quién escaneó: el usuario de portería, no el empleado.
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('gate', 60)->nullable();
            $table->text('notes')->nullable();

            // Sin softDeletes a propósito: bitácora.
            $table->timestampsTz(0);

            $table->index(['tenant_id', 'employee_id', 'scanned_at']);
            $table->index(['tenant_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_scans');
        Schema::dropIfExists('hr_employee_qr_codes');
        Schema::dropIfExists('hr_employees');
    }
};
