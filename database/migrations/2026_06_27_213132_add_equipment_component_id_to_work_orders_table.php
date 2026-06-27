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
            $table->foreignUuid('equipment_component_id')->nullable()->constrained('equipment_components')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropForeign(['equipment_component_id']);
            $table->dropColumn('equipment_component_id');
        });
    }
};
