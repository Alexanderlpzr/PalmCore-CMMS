<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('maintenance_plan_id')->constrained('maintenance_plans')->cascadeOnDelete();

            // Last execution state
            $table->timestampTz('last_completed_at', 0)->nullable();
            $table->decimal('last_completed_meter', 10, 1)->nullable();

            // Next due — populated after each execution
            $table->timestampTz('next_due_at', 0)->nullable();
            $table->decimal('next_due_meter', 10, 1)->nullable();

            // Historical counter
            $table->integer('times_executed')->default(0)->unsigned();
            $table->integer('times_skipped')->default(0)->unsigned(); // cycles advanced past due date

            // Link to most recently generated WO
            $table->foreignUuid('last_work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();

            $table->timestampsTz(0);
            // No soft deletes — operational record

            $table->unique('maintenance_plan_id'); // enforces 1:1

            // Overdue detection indexes (used by future auto-generation job)
            $table->index(['tenant_id', 'next_due_at']);
            $table->index(['tenant_id', 'next_due_meter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
