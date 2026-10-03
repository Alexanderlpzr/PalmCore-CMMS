<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anular una marca sin borrarla.
 *
 * La marca es la prueba de a qué hora cruzó alguien la puerta, y de ahí sale lo que se
 * le paga: no se borra nunca. Pero portería se equivoca —el carné del compañero, una
 * salida de más— y una marca manual no deshace una marca sobrante. Anulada, deja de
 * contar para las horas y sigue a la vista, con quién la anuló y por qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_attendance_scans', function (Blueprint $table) {
            $table->timestampTz('voided_at', 0)->nullable()->after('notes');
            $table->foreignUuid('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendance_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
