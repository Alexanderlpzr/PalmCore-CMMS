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
        Schema::create('component_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_component_id')->constrained('equipment_components')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->text('description')->nullable();
            $table->decimal('worked_hours_at_event', 8, 2)->nullable()->unsigned();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['equipment_component_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_histories');
    }
};
