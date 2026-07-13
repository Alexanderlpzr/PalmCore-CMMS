<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plant's headline number.
 *
 * «Eficiencia de planta = horas efectivas / horas programadas» (91,46% in June
 * 2026: 413,4 h of 452 h). The system could not produce it, for a simple reason:
 * nothing recorded how many hours the plant was *supposed* to run. Availability
 * without a programmed baseline is a number about machines, not about the plant.
 *
 * `production_calendar` is that baseline: one row per plant per day, filled by
 * the planner. `plant_monthly_kpis` freezes the month once it is closed, so the
 * figure reported to management never silently changes afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_calendar', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->constrained('plants')->cascadeOnDelete();

            $table->date('calendar_date');
            // How many hours the plant was scheduled to run that day. Zero is a
            // legitimate value: a Sunday with no fruit is not a bad day, it is a
            // day that was never meant to produce.
            $table->decimal('programmed_hours', 5, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestampsTz(0);

            $table->unique(['plant_id', 'calendar_date']);
            $table->index(['tenant_id', 'calendar_date']);
        });

        Schema::create('plant_monthly_kpis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('plant_id')->constrained('plants')->cascadeOnDelete();

            $table->smallInteger('year');
            $table->smallInteger('month');

            $table->decimal('programmed_hours', 8, 2)->default(0);
            $table->decimal('lost_hours', 8, 2)->default(0);
            $table->decimal('effective_hours', 8, 2)->default(0);
            // Hours lost to causes maintenance actually owns, isolated from the
            // ones it merely suffers (falta de fruta, corte de energía).
            $table->decimal('maintenance_lost_hours', 8, 2)->default(0);

            $table->integer('failure_count')->default(0);
            $table->decimal('mtbf_hours', 10, 2)->nullable();
            $table->decimal('mttr_hours', 10, 2)->nullable();

            $table->timestampTz('calculated_at', 0);
            $table->timestampsTz(0);

            $table->unique(['plant_id', 'year', 'month']);
            $table->index(['tenant_id', 'year', 'month']);
        });

        // Efficiency is a ratio of two stored columns — let the database derive it
        // so no caller can ever report a different number than the one on record.
        DB::statement(<<<'SQL'
            ALTER TABLE plant_monthly_kpis
            ADD COLUMN efficiency_percentage NUMERIC(5,2)
            GENERATED ALWAYS AS (
                CASE WHEN programmed_hours > 0
                     THEN ROUND((effective_hours / programmed_hours) * 100, 2)
                     ELSE NULL
                END
            ) STORED
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_monthly_kpis');
        Schema::dropIfExists('production_calendar');
    }
};
