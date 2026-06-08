<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_time_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');

            $table->timestampTz('started_at', 0);
            $table->timestampTz('ended_at', 0)->nullable();  // null = session still open
            $table->decimal('hours', 6, 2)->nullable();       // computed or manual override
            $table->text('description')->nullable();

            $table->timestampsTz(0);
            // No soft deletes — audit log

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_time_logs');
    }
};
