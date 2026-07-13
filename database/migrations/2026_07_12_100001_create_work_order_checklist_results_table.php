<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_checklist_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_task_id')->constrained('work_order_tasks')->cascadeOnDelete();

            // Provenance only — the definition below is the frozen copy.
            $table->foreignUuid('maintenance_checklist_item_id')->nullable()
                ->constrained('maintenance_checklist_items')->nullOnDelete();

            // ── Snapshot of the item definition (frozen at generation) ─────────
            $table->smallInteger('sort_order')->default(0);
            $table->string('label', 500);
            $table->string('item_type', 20);        // MaintenanceChecklistItemType enum
            $table->string('unit', 30)->nullable();
            $table->decimal('expected_min', 10, 3)->nullable();
            $table->decimal('expected_max', 10, 3)->nullable();
            $table->boolean('is_required')->default(true);

            // ── Recorded value (exactly one is populated, per item_type) ───────
            $table->boolean('value_boolean')->nullable();
            $table->decimal('value_numeric', 12, 3)->nullable();
            $table->text('value_text')->nullable();

            $table->string('photo_path', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('recorded_at', 0)->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index(['work_order_task_id', 'sort_order']);
            $table->index('tenant_id');
        });

        // Deviation is a deterministic function of the recorded value and the
        // frozen tolerances — never a field anyone can get wrong by hand.
        DB::statement(<<<'SQL'
            ALTER TABLE work_order_checklist_results
            ADD COLUMN is_out_of_range BOOLEAN
            GENERATED ALWAYS AS (
                value_numeric IS NOT NULL
                AND (
                    (expected_min IS NOT NULL AND value_numeric < expected_min)
                    OR (expected_max IS NOT NULL AND value_numeric > expected_max)
                )
            ) STORED
        SQL);

        // Query path: "show me every deviation" (dashboard + alerting).
        DB::statement(
            'CREATE INDEX work_order_checklist_results_out_of_range_idx
             ON work_order_checklist_results (tenant_id)
             WHERE is_out_of_range AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_checklist_results');
    }
};
