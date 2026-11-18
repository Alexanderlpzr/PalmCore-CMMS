<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 del módulo comercial: la cartera por antigüedad y los avisos.
 *
 *  - El plazo de pago de cada cliente: una factura vence en su fecha más ese plazo.
 *  - Las marcas de «ya se avisó» de cada factura vencida, cada cargue sin facturar y cada
 *    oferta a punto de cumplirse, para que el aviso diario no repita lo mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->default(30)->after('market');
        });

        Schema::table('sales_dispatches', function (Blueprint $table) {
            $table->timestampTz('overdue_notified_at')->nullable()->after('paid_on');
            $table->timestampTz('uninvoiced_notified_at')->nullable()->after('overdue_notified_at');
        });

        Schema::table('sales_offers', function (Blueprint $table) {
            $table->timestampTz('nearly_closed_notified_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('sales_offers', function (Blueprint $table) {
            $table->dropColumn('nearly_closed_notified_at');
        });

        Schema::table('sales_dispatches', function (Blueprint $table) {
            $table->dropColumn(['overdue_notified_at', 'uninvoiced_notified_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('payment_terms_days');
        });
    }
};
