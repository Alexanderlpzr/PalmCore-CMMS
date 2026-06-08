<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID v7 — generado en PHP por HasUuids::newUniqueId()

            // FK única — enforce 1:1 con users a nivel de base de datos
            $table->uuid('user_id')->unique();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete(); // El perfil se elimina cuando el usuario es hard-deleted (forceDelete)

            // Contacto y presencia
            $table->string('avatar_path', 500)->nullable(); // Filament delega a getFilamentAvatarUrl()
            $table->string('phone', 30)->nullable();
            $table->string('job_title', 100)->nullable();   // "Coordinador de Mantenimiento"

            // Preferencias de idioma y región
            // preferred_language: activa el paquete de traducción (es, en)
            // locale: controla formato de fechas, moneda y números (es_CO, es_EC, es_HN)
            // Resolución en app: user_profiles → tenant → config('app.locale')
            $table->string('preferred_language', 10)->nullable();
            $table->string('locale', 10)->nullable();

            // Timezone personal — override del timezone del tenant
            $table->string('timezone', 50)->nullable();

            // Descripción libre
            $table->text('bio')->nullable();

            // Sin softDeletes: el perfil no tiene historial propio.
            // Si el usuario se restaura (restore()), se recrea el perfil vía Observer.
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
