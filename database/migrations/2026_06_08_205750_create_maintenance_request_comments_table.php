<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_request_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');

            $table->text('body');
            $table->boolean('is_internal')->default(false); // internal note vs. public comment

            $table->timestampsTz(0);
            // No soft deletes — comments are audit records; they are never deleted

            $table->index(['maintenance_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_comments');
    }
};
