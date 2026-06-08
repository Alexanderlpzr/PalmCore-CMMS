<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Numbering: OT-YYYY-{EQUIPMENT_CODE}-000001
            $table->string('work_order_number', 30);

            // Source
            $table->foreignUuid('maintenance_request_id')->nullable()->constrained('maintenance_requests')->nullOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->constrained('plants')->cascadeOnDelete();
            $table->foreignUuid('area_id')->nullable()->constrained('areas')->nullOnDelete();

            // Classification
            $table->string('work_order_type', 30);   // WorkOrderType enum
            $table->string('status', 30);             // WorkOrderStatus enum
            $table->string('priority', 20);           // WorkOrderPriority enum

            // Content
            $table->string('title', 255);
            $table->text('description');
            $table->text('instructions')->nullable();
            $table->text('failure_cause')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('rejection_reason')->nullable();

            // Equipment impact
            $table->boolean('equipment_stopped')->default(false);
            $table->integer('downtime_minutes')->nullable()->unsigned();

            // Planning
            $table->timestampTz('planned_start_at', 0)->nullable();
            $table->timestampTz('planned_end_at', 0)->nullable();
            $table->decimal('planned_labor_hours', 8, 2)->nullable();

            // Execution
            $table->timestampTz('actual_start_at', 0)->nullable();
            $table->timestampTz('actual_end_at', 0)->nullable();
            $table->decimal('actual_labor_hours', 8, 2)->nullable();  // computed from time_logs

            // Costs
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost_labor', 15, 2)->nullable();    // computed
            $table->decimal('actual_cost_parts', 15, 2)->nullable();    // computed
            $table->decimal('actual_cost_external', 15, 2)->nullable(); // manual (contractors etc.)
            $table->decimal('actual_cost_total', 15, 2)->nullable();    // computed sum
            $table->char('currency_code', 3)->default('COP');

            // Tracking actors
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('assigned_supervisor')->nullable()->constrained('users');
            $table->foreignUuid('completed_by')->nullable()->constrained('users');
            $table->foreignUuid('verified_by')->nullable()->constrained('users');

            // Transition timestamps
            $table->timestampTz('started_at', 0)->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->timestampTz('verified_at', 0)->nullable();
            $table->timestampTz('closed_at', 0)->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->unique(['tenant_id', 'work_order_number']);
            $table->index(['tenant_id', 'status']);
            $table->index('equipment_id');
            $table->index('maintenance_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
