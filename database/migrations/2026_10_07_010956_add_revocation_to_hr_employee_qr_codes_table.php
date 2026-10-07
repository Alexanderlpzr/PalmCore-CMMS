<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién anuló un carné, cuándo y por qué.
 *
 * Reemitir un carné deja de inmediato sin servicio el que el trabajador tiene en el
 * bolsillo. El viejo ya se conservaba —los escaneos históricos apuntan a él—, pero sin
 * decir quién lo anuló ni por qué: el historial del trabajador solo mostraba que hubo
 * otro. Ahora el carné anulado lo dice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employee_qr_codes', function (Blueprint $table) {
            $table->foreignUuid('revoked_by')->nullable()->after('scan_count')->constrained('users')->nullOnDelete();
            $table->timestampTz('revoked_at', 0)->nullable()->after('revoked_by');
            $table->string('revocation_reason', 20)->nullable()->after('revoked_at');
            $table->text('revocation_detail')->nullable()->after('revocation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employee_qr_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['revoked_at', 'revocation_reason', 'revocation_detail']);
        });
    }
};
