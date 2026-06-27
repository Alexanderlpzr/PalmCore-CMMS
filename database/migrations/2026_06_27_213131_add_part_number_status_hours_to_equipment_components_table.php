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
        Schema::table('equipment_components', function (Blueprint $table): void {
            $table->string('part_number', 100)->nullable()->after('serial_number');
            $table->string('status', 20)->default('active')->after('part_number');
            $table->decimal('worked_hours', 8, 2)->nullable()->unsigned()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_components', function (Blueprint $table): void {
            $table->dropColumn(['part_number', 'status', 'worked_hours']);
        });
    }
};
