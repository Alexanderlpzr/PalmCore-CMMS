<?php

use App\Models\AutomationRule;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;

/**
 * La alerta de repuestos por reponer encendida en cada empresa que ya existe; las nuevas
 * la reciben al aprovisionarse. Idempotente: no toca la que ya tenga una regla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()->each(fn (Tenant $tenant) => AutomationRule::ensureStockLowRule($tenant->id));
    }

    public function down(): void
    {
        // La regla queda: es un dato de cada empresa, no estructura.
    }
};
