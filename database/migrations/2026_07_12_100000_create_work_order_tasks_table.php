<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();

            // Provenance only — never read at execution time. The plan may change
            // or be deleted; the OT keeps its own frozen copy of the work.
            $table->foreignUuid('maintenance_plan_task_id')->nullable()
                ->constrained('maintenance_plan_tasks')->nullOnDelete();

            // ── Snapshot from the plan (frozen at generation) ──────────────────
            $table->smallInteger('sort_order')->default(0);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->smallInteger('estimated_minutes')->nullable()->unsigned();

            // ── Execution ─────────────────────────────────────────────────────
            $table->string('status', 20)->default('pending'); // WorkOrderTaskStatus enum
            $table->text('skipped_reason')->nullable();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at', 0)->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index(['work_order_id', 'sort_order']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_tasks');
    }
};
