<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('body')->nullable();
            $table->string('category', 50)->default('news');
            $table->string('image_path')->nullable();
            $table->string('button_label', 100)->nullable();
            $table->string('button_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_pinned')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampTz('published_at')->useCurrent();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
