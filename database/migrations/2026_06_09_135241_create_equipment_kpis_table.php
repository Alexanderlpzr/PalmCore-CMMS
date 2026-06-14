<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_kpis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            // Rolling window configuration — configurable per tenant via settings JSONB
            $table->smallInteger('period_months')->default(12);
            $table->date('period_start');
            $table->date('period_end');

            // Corrective-failure KPIs (was_planned = false events only)
            $table->decimal('mtbf_hours', 10, 2)->nullable();
            $table->decimal('mttr_hours', 10, 2)->nullable();
            $table->decimal('unplanned_availability_percentage', 5, 2)->nullable();

            // Total availability (all downtime: planned + unplanned)
            $table->decimal('availability_percentage', 5, 2)->nullable();

            // Raw counters backing the KPI formulas
            $table->unsignedInteger('failure_count')->default(0);
            $table->decimal('downtime_hours', 10, 2)->default(0); // unplanned downtime only

            $table->timestampTz('last_failure_at', 0)->nullable();
            $table->timestampTz('last_calculated_at', 0);

            // Staleness flag: true = needs recalculation; false = fresh
            $table->boolean('is_stale')->default(true);

            $table->softDeletesTz(precision: 0);
            $table->timestampsTz(0);
        });

        // Regular UNIQUE (not partial) — service uses withTrashed() so the single row
        // per equipment is always found and restored, never duplicated.
        Schema::table('equipment_kpis', function (Blueprint $table) {
            $table->unique(['tenant_id', 'equipment_id'], 'equipment_kpis_unique_per_equipment');
        });

        // Partial indexes for performance-critical query paths
        DB::statement(
            'CREATE INDEX equipment_kpis_stale_idx ON equipment_kpis (tenant_id, is_stale) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE INDEX equipment_kpis_equipment_idx ON equipment_kpis (equipment_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_kpis');
    }
};
