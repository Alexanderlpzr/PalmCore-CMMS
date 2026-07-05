<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_components', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->nullable()->unsigned()->after('useful_life_hours');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_components', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
