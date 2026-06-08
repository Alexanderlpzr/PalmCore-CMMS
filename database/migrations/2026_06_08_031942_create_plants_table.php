<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plants', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID v7 — generado en PHP por HasUuids::newUniqueId()

            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            // Identificación
            $table->string('code', 30);    // "PLT-01" — único por tenant (ver UNIQUE abajo)
            $table->string('name', 255);   // "Extractora El Pajuil"

            // Ubicación geográfica — dos niveles:
            // 1. Dirección textual de acceso ("Km 14 vía Tumaco-Pasto, corregimiento El Diviso")
            // 2. Coordenadas GPS para mapas y verificación QR de presencia del técnico
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Ubicación administrativa
            $table->string('city', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->char('country_code', 3)->nullable(); // ISO 3166-1 alpha-3: COL, ECU, HND

            // Timezone de la planta — null hereda de tenant.timezone
            $table->string('timezone', 50)->nullable();

            $table->boolean('is_active')->default(true);

            // Código único dentro del tenant — "PLT-01" en Empresa A ≠ "PLT-01" en Empresa B
            $table->unique(['tenant_id', 'code'], 'plants_tenant_code_unique');

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Cubre TenantScope + filtro de activos frecuente en dropdowns y selectores
        DB::statement('CREATE INDEX plants_tenant_active_idx ON plants (tenant_id, is_active)');
    }

    public function down(): void
    {
        Schema::dropIfExists('plants');
    }
};
