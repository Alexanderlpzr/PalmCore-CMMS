<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El vale de salida: por qué salió el repuesto y quién se lo llevó. Y el despacho desde
 * el almacén: en las empresas que ya existen, los repuestos de una OT salen cuando el
 * almacén los entrega, no al cerrar la orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->string('exit_reason', 30)->nullable()->after('reference_number');
            $table->string('recipient_name', 150)->nullable()->after('exit_reason');
        });

        Tenant::query()->each(function (Tenant $tenant): void {
            $tenant->forceFill([
                'settings' => array_replace_recursive($tenant->settings ?? [], ['inventory' => ['warehouse_dispatch' => true]]),
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn(['exit_reason', 'recipient_name']);
        });
    }
};
