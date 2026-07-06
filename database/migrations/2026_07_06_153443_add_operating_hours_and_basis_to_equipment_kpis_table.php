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
        Schema::table('equipment_kpis', function (Blueprint $table): void {
            // Operating hours from the equipment's hour-meter over the period,
            // when available. Feeds a truer MTBF than calendar time for equipment
            // that does not run 24/7 (e.g. seasonal extractor lines).
            $table->decimal('operating_hours', 10, 2)->nullable()->after('downtime_hours');
            // Which basis produced mtbf_hours: 'meter' (operating hours) or
            // 'calendar' (elapsed period hours, the fallback).
            $table->string('mtbf_basis', 10)->default('calendar')->after('operating_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_kpis', function (Blueprint $table): void {
            $table->dropColumn(['operating_hours', 'mtbf_basis']);
        });
    }
};
