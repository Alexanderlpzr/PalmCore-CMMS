<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién entró, cuándo, desde dónde — para auditoría. Sin tenant_id: el login
 * ocurre antes de que Filament resuelva a qué empresa se conecta, y un usuario
 * puede pertenecer a varias. «Por empresa» se responde filtrando por las
 * empresas del usuario (user.tenants), no con una columna en este registro.
 *
 * user_id es nullable a propósito: un intento fallido con un correo que no
 * existe no tiene usuario que enlazar, pero sigue siendo la fila más
 * interesante para una auditoría (fuerza bruta, credenciales filtradas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->references('id')->on('users')->nullOnDelete();
            $table->string('email', 255);
            $table->string('event', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('occurred_at');

            $table->index(['event', 'occurred_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
