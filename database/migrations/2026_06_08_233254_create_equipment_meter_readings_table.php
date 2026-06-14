<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_meter_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            $table->decimal('reading_value', 10, 1);
            $table->string('reading_unit', 20)->default('hours'); // MeterReadingUnit enum
            $table->timestampTz('recorded_at', 0);
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            // No soft deletes — audit trail is immutable

            $table->index(['equipment_id', 'recorded_at']);
            $table->index(['tenant_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_meter_readings');
    }
};
