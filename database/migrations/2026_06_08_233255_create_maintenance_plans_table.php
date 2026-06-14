<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            // Numbering: PM-{EQUIPMENT_CODE}-{FREQUENCY_LABEL}
            $table->string('plan_number', 50);

            $table->string('name', 255);
            $table->text('description')->nullable();

            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Trigger / frequency
            $table->string('trigger_source', 20);       // MaintenanceTriggerSource enum
            $table->string('time_frequency', 20)->nullable(); // MaintenanceTimeFrequency enum
            $table->integer('meter_interval')->nullable()->unsigned(); // free integer: 500, 1000, 2000…

            // Cadence mode — fixed: anchored to theoretical date; floating: from last real completion
            $table->string('cadence_mode', 20)->default('fixed'); // 'fixed' | 'floating'

            // Operational flags
            $table->boolean('pause_when_equipment_inactive')->default(false);
            $table->smallInteger('grace_period_days')->nullable()->unsigned();
            $table->integer('grace_meter_hours')->nullable()->unsigned();

            $table->smallInteger('estimated_duration_minutes')->nullable()->unsigned();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_generated_at', 0)->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->unique(['tenant_id', 'plan_number']);
            $table->index(['tenant_id', 'equipment_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};
