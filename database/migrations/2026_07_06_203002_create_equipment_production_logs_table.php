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
        Schema::create('equipment_production_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            $table->date('log_date');
            $table->string('shift', 40)->nullable();

            // Inputs for OEE = Availability × Performance × Quality
            $table->integer('planned_minutes');                 // planned production time
            $table->integer('downtime_minutes')->default(0);    // stoppages during the period
            $table->decimal('ideal_rate_per_hour', 12, 4);      // units/hour at full speed
            $table->decimal('total_units', 14, 2)->default(0);  // produced (good + reject)
            $table->decimal('good_units', 14, 2)->default(0);   // passed quality

            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletesTz('deleted_at', 0);
            $table->timestampsTz(0);

            $table->index(['tenant_id', 'log_date']);
            $table->index(['tenant_id', 'equipment_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_production_logs');
    }
};
