<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plan_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('maintenance_plan_id')->constrained('maintenance_plans')->cascadeOnDelete();

            $table->string('attachment_label', 100)->nullable(); // "Manual SKF", "Procedimiento WEG"
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();

            $table->foreignUuid('uploaded_by')->constrained('users');

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index('maintenance_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plan_attachments');
    }
};
