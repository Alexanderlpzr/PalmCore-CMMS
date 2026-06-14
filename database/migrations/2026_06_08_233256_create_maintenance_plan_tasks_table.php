<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('maintenance_plan_id')->constrained('maintenance_plans')->cascadeOnDelete();

            $table->smallInteger('sort_order')->default(0);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->smallInteger('estimated_minutes')->nullable()->unsigned();

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index(['maintenance_plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_tasks');
    }
};
