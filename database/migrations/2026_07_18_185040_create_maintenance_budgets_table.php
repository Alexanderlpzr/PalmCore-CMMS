<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El presupuesto que la gerencia le asigna al área de mantenimiento, mes a mes.
 *
 * Es el denominador del control de gastos: sin un monto asignado, el sistema
 * puede decir cuánto se gastó, pero no si eso estuvo dentro o fuera de lo que
 * la planta tenía para gastar. Uno por planta y por mes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->constrained('plants')->cascadeOnDelete();

            $table->smallInteger('year');
            $table->smallInteger('month');

            // Lo asignado para el mes, en la moneda del tenant.
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('notes')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(0);

            // Un solo presupuesto por planta y mes: fijarlo dos veces corrige el
            // monto, no crea una segunda fila que descuadre el total.
            $table->unique(['plant_id', 'year', 'month']);
            $table->index(['tenant_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_budgets');
    }
};
