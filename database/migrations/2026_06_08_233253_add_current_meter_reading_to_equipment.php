<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            // Denormalized cache — updated by observer when a meter reading is recorded
            $table->decimal('current_meter_reading', 10, 1)->nullable()->after('is_active');
            $table->string('meter_unit', 20)->nullable()->default('hours')->after('current_meter_reading');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn(['current_meter_reading', 'meter_unit']);
        });
    }
};
