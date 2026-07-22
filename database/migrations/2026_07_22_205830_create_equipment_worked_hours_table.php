<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_worked_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            $table->string('period_type', 20); // WorkedHoursPeriodType enum: diario|semanal
            $table->date('log_date');
            $table->decimal('hours', 6, 2);
            $table->foreignUuid('recorded_by')->constrained('users');
            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            // No soft deletes — audit trail is immutable, igual que equipment_meter_readings

            $table->index(['tenant_id', 'equipment_id', 'log_date']);
            $table->index(['equipment_id', 'period_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_worked_hours');
    }
};
