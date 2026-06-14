<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->references('id')->on('tenants');
            $table->foreignUuid('user_id')->references('id')->on('users');
            $table->string('activity_type', 30);  // time_log|comment|photo|signature|status_change
            $table->uuid('activity_id');           // polymorphic — no FK constraint (evidence persists)
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 6, 2);     // metres
            $table->string('source', 20)->default('unknown');  // gps|network|unknown
            $table->boolean('is_low_accuracy')->default(false);
            $table->timestampTz('captured_at');    // device GPS timestamp
            $table->timestampTz('created_at');     // server ingestion time (no updated_at — immutable)

            $table->index('tenant_id');
            $table->index('user_id');
            $table->index(['activity_type', 'activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_locations');
    }
};
