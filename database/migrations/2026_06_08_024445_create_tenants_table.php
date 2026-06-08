<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identidad
            $table->string('name', 255);
            $table->string('slug', 100)->unique();          // subdominio: {slug}.palmcore.app
            $table->string('tax_id', 50)->nullable();       // NIT / RUC / RIF

            // Contacto operativo (independiente del usuario administrador)
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();

            // Localización
            $table->char('country_code', 3)->nullable();    // ISO 3166-1 alpha-3: COL, ECU, HND
            $table->string('timezone', 50)->default('UTC');
            $table->string('locale', 10)->default('es_CO'); // mercado inicial Colombia

            // Suscripción
            $table->string('subscription_plan', 50)->default('starter');
            $table->date('subscription_expires_at')->nullable();

            // Estado
            $table->boolean('is_active')->default(true);
            $table->string('logo_path', 500)->nullable();

            // Overrides de configuración por empresa (config/palmcore.php)
            $table->jsonb('settings')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Índice parcial PostgreSQL — solo filas activas.
        // La query del middleware es: WHERE slug = ? AND is_active = true
        // El índice parcial es más pequeño y eficiente que uno compuesto completo.
        DB::statement('CREATE INDEX tenants_active_slug_idx ON tenants (slug) WHERE is_active = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
