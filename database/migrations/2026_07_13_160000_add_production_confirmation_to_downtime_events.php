<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A5 — la firma de producción sobre el paro.
 *
 * Las horas perdidas se le restan a la planta, no a mantenimiento. Hasta hoy las
 * declaraba mantenimiento solo: el mismo que sale mal en la foto si el número es
 * alto es el que escribe el número. El jefe de turno tiene que poder decir «sí,
 * estuvimos abajo esas horas» — o decir que no y dejar constancia de que no.
 *
 * `pending` no es un defecto del registro: es un paro que todavía nadie de
 * producción miró. Los tres estados se distinguen a propósito, porque «sin firmar»
 * y «firmado en desacuerdo» son cosas distintas y las dos importan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->string('confirmation_status', 15)->default('pending')->after('registered_by');
            $table->foreignUuid('confirmed_by')->nullable()->after('confirmation_status')
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at', 0)->nullable()->after('confirmed_by');
            $table->text('confirmation_notes')->nullable()->after('confirmed_at');

            // «¿Qué falta por firmar de esta planta?» — la pregunta del lunes.
            $table->index(['plant_id', 'confirmation_status']);
        });

        // Una firma sin firmante no es una firma. Y una disputa sin motivo es una
        // firma que no dice nada: si producción no está de acuerdo, tiene que
        // escribir por qué. La base de datos lo exige; PHP solo lo traduce.
        DB::statement(<<<'SQL'
            ALTER TABLE equipment_downtime_events
            ADD CONSTRAINT equipment_downtime_events_confirmation_coherent CHECK (
                (confirmation_status = 'pending'
                    AND confirmed_by IS NULL
                    AND confirmed_at IS NULL)
                OR (confirmation_status = 'confirmed'
                    AND confirmed_by IS NOT NULL
                    AND confirmed_at IS NOT NULL)
                OR (confirmation_status = 'disputed'
                    AND confirmed_by IS NOT NULL
                    AND confirmed_at IS NOT NULL
                    AND confirmation_notes IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE equipment_downtime_events DROP CONSTRAINT IF EXISTS equipment_downtime_events_confirmation_coherent');

        Schema::table('equipment_downtime_events', function (Blueprint $table) {
            $table->dropIndex(['plant_id', 'confirmation_status']);
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['confirmation_status', 'confirmed_at', 'confirmation_notes']);
        });
    }
};
