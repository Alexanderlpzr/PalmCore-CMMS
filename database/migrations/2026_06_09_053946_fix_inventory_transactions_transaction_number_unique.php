<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            // Replace global unique with per-tenant unique
            $table->dropUnique('inventory_transactions_transaction_number_unique');
            $table->unique(['tenant_id', 'transaction_number']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'transaction_number']);
            $table->unique('transaction_number');
        });
    }
};
