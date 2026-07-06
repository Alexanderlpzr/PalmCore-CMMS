<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            // Standardised failure classification captured at completion, alongside
            // the free-text failure_cause/root_cause. Feeds Pareto-by-mode analysis.
            $table->string('failure_mode', 40)->nullable()->after('root_cause');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn('failure_mode');
        });
    }
};
