<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID v7 — generado en PHP por HasUuids::newUniqueId()

            // Identidad y autenticación
            $table->string('name', 255);
            $table->string('email', 255)->unique(); // Único global — un email = una persona en todo el sistema
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password')->nullable(); // Nullable: usuarios que solo usan passkeys no tienen contraseña
            $table->rememberToken();                // Fortify "remember me"

            // Control de acceso
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_super_admin')->default(false); // Gate::before bypass — solo staff interno de PalmCore

            // Auditoría de sesión
            $table->timestampTz('last_login_at')->nullable();
            // last_login_ip usa el tipo nativo inet de PostgreSQL (valida IPv4/IPv6 a nivel de motor)
            // Se agrega vía raw statement porque Blueprint no tiene método inet()

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('ALTER TABLE users ADD COLUMN last_login_ip inet');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary(); // Fortify resuelve tokens por email — PK string es correcto aquí
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index(); // UUID — mismo tipo que users.id; sin FK para guests
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index(); // Unix timestamp — el tipo int es correcto aquí
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
