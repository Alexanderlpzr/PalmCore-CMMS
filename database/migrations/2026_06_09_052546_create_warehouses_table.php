<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('updated_by')->nullable()->constrained('users');
            $table->softDeletesTz('deleted_at', 0);
            $table->timestampsTz(0);

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
