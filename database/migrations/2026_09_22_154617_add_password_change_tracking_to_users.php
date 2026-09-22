<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contraseñas temporales que caducan en el primer ingreso.
 *
 * Cuando el superadministrador le pone una contraseña a alguien —porque la olvidó, o
 * porque la cuenta es nueva—, esa contraseña la conocen dos personas. `must_change_password`
 * obliga al dueño a reemplazarla por una propia apenas entra, y así la definitiva solo
 * la conoce él. `password_changed_at` es lo que el panel muestra para saber si una
 * cuenta sigue con la contraseña que alguien más le dio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestampTz('password_changed_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_changed_at']);
        });
    }
};
