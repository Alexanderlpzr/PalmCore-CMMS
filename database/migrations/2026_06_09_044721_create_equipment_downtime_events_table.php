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
        Schema::create('equipment_downtime_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('work_order_number', 50)->nullable();
            $table->timestampTz('started_at', 0);
            $table->timestampTz('ended_at', 0)->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('cause_type', 30);
            $table->boolean('was_planned')->default(false);
            $table->string('failure_mode', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz(0);

            $table->unique('work_order_id');
            $table->index(['equipment_id', 'started_at']);
            $table->index(['tenant_id', 'equipment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_downtime_events');
    }
};
