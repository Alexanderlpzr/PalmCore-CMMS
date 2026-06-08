<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Classification
            $table->string('document_type', 50);
            $table->string('title', 255);
            $table->text('description')->nullable();

            // File
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();

            // Versioning & lifecycle
            $table->string('version', 50)->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::table('equipment_documents', function (Blueprint $table): void {
            $table->index(['equipment_id', 'document_type']);
            $table->index(['tenant_id', 'document_type']);
            $table->index('expires_at');
        });

        // Unique document title+version per equipment (soft-delete aware)
        \DB::statement(
            'CREATE UNIQUE INDEX equipment_documents_title_version_unique
             ON equipment_documents (equipment_id, title, version)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_documents');
    }
};
