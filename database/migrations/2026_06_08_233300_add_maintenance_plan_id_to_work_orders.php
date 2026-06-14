<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignUuid('maintenance_plan_id')
                ->nullable()
                ->after('maintenance_request_id')
                ->constrained('maintenance_plans')
                ->nullOnDelete();

            $table->index('maintenance_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['maintenance_plan_id']);
            $table->dropIndex(['maintenance_plan_id']);
            $table->dropColumn('maintenance_plan_id');
        });
    }
};
