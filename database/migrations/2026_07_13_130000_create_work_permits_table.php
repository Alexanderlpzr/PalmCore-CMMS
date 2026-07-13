<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A2 — nadie entra a un espacio confinado sin permiso.
 *
 * Un permiso de trabajo no es un campo de texto ni un adjunto: es un flujo con dos
 * firmas (quien lo emite y quien lo recibe), una vigencia con hora, unos puntos de
 * aislamiento verificados, y un bloqueo real de la ejecución. Si el sistema deja
 * arrancar el trabajo sin él, el permiso es papel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            // Qué permisos exige ESTE trabajo. Lo declara quien planifica, porque es
            // quien sabe que hay que soldar sobre la nave o entrar al digestor.
            $table->jsonb('required_permit_types')->nullable()->after('equipment_stopped');
        });

        Schema::create('work_permits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();

            $table->string('permit_number', 40);
            $table->string('permit_type', 30);                   // WorkPermitType
            $table->string('status', 20)->default('issued');     // WorkPermitStatus

            $table->text('hazards');                             // ATS: qué puede pasar
            $table->text('controls');                            // ATS: qué lo evita
            // LOTO: qué se bloqueó y con qué candado. Sin esto, el equipo sigue vivo.
            $table->jsonb('isolation_points')->nullable();

            // Vigencia con hora: un permiso «del día» es un permiso sin vigencia.
            $table->timestampTz('valid_from', 0);
            $table->timestampTz('valid_until', 0);

            // Las dos firmas. Emitir no es aceptar.
            $table->foreignUuid('issued_by')->constrained('users');
            $table->timestampTz('issued_at', 0);
            $table->foreignUuid('accepted_by')->nullable()->constrained('users');
            $table->timestampTz('accepted_at', 0)->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users');
            $table->timestampTz('closed_at', 0)->nullable();

            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            // No soft deletes — un permiso es un registro legal

            $table->unique(['tenant_id', 'permit_number']);
            $table->index(['work_order_id', 'permit_type']);
        });

        // Un permiso que termina antes de empezar no protege a nadie.
        DB::statement(
            'ALTER TABLE work_permits
             ADD CONSTRAINT work_permits_validity_check
             CHECK (valid_until > valid_from)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('work_permits');

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('required_permit_types');
        });
    }
};
