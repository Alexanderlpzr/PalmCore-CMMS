<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_checklist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('maintenance_plan_task_id')->constrained('maintenance_plan_tasks')->cascadeOnDelete();

            $table->smallInteger('sort_order')->default(0);
            $table->string('label', 500);
            $table->string('item_type', 20);        // MaintenanceChecklistItemType enum
            $table->string('unit', 30)->nullable();  // °C, bar, mm, RPM, etc.

            // Range validation for numeric items
            $table->decimal('expected_min', 10, 3)->nullable();
            $table->decimal('expected_max', 10, 3)->nullable();

            $table->boolean('is_required')->default(true);

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index(['maintenance_plan_task_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_checklist_items');
    }
};
