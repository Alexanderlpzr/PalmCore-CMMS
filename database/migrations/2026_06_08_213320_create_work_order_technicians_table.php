<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_technicians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');

            $table->string('role', 30);                         // TechnicianRole enum
            $table->decimal('planned_hours', 6, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();  // frozen at assignment time
            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            // No soft deletes — removal is operational

            $table->unique(['work_order_id', 'user_id']);
            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_technicians');
    }
};
