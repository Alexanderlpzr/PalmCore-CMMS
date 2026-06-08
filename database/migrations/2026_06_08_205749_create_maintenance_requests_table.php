<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Numbering: MR-YYYY-00001 (sequential per tenant per year)
            $table->string('request_number', 20);

            // Source
            $table->foreignUuid('issue_report_id')->nullable()->constrained('equipment_issue_reports')->nullOnDelete();
            $table->foreignUuid('equipment_id')->constrained('equipment')->cascadeOnDelete();

            // Classification
            $table->string('request_type', 30);   // MaintenanceRequestType enum
            $table->string('priority', 20);        // MaintenanceRequestPriority enum
            $table->string('status', 30);          // MaintenanceRequestStatus enum

            // Content
            $table->string('title', 255);
            $table->text('description');
            $table->date('requested_due_date')->nullable();
            $table->text('rejection_reason')->nullable();

            // Tracking
            $table->foreignUuid('created_by')->constrained('users');
            $table->foreignUuid('assigned_reviewer')->nullable()->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->foreignUuid('rejected_by')->nullable()->constrained('users');

            $table->timestampTz('submitted_at', 0)->nullable();
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->timestampTz('approved_at', 0)->nullable();
            $table->timestampTz('rejected_at', 0)->nullable();

            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->index('tenant_id');
            $table->index('equipment_id');
            $table->index('status');
            $table->unique(['tenant_id', 'request_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
