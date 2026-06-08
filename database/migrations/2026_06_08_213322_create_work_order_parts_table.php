<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();

            $table->string('part_code', 100)->nullable();  // free text, no FK — no inventory yet
            $table->string('description', 255);
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 30)->nullable();        // pcs / kg / L / m / etc.
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable(); // computed: quantity * unit_cost

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_parts');
    }
};
