<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID v7 — generado en PHP por HasUuids::newUniqueId()

            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            // Un usuario no puede aparecer dos veces en el mismo tenant
            $table->unique(['tenant_id', 'user_id']);

            // Tenant por defecto del usuario al hacer login sin subdominio
            $table->boolean('is_primary_tenant')->default(false);

            // Propietario de la cuenta — status especial, no es un rol de Spatie.
            // No puede ser removido sin transferir ownership primero.
            $table->boolean('is_owner')->default(false);

            // Cuándo se unió el usuario a este tenant
            $table->timestampTz('joined_at');

            // Quién invitó a este usuario (nullable — null si fue creado directamente)
            $table->uuid('invited_by')->nullable();
            $table->foreign('invited_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete(); // Si el invitador es eliminado, la membresía del invitado persiste

            $table->timestampsTz();
        });

        // Índice para "¿en qué tenants está este usuario?"
        DB::statement('CREATE INDEX tenant_users_user_id_idx ON tenant_users (user_id)');

        // Partial index: un usuario tiene como máximo UN tenant primario
        DB::statement('CREATE UNIQUE INDEX tenant_users_primary_per_user_idx ON tenant_users (user_id) WHERE is_primary_tenant = true');

        // Partial index: un tenant tiene exactamente UN owner activo
        DB::statement('CREATE UNIQUE INDEX tenant_users_owner_per_tenant_idx ON tenant_users (tenant_id) WHERE is_owner = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
