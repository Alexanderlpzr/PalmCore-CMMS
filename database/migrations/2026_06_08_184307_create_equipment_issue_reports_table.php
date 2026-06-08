<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_issue_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('equipment_id')
                ->constrained('equipment')
                ->cascadeOnDelete();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Which QR scan session originated this report (nullable: could come from other channels)
            $table->foreignUuid('qr_code_id')
                ->nullable()
                ->constrained('equipment_qr_codes')
                ->nullOnDelete();

            // Report content
            $table->text('description');
            $table->string('severity', 20)->default('medium');

            // Reporter identity (may be anonymous)
            $table->string('reporter_name', 255)->nullable();
            $table->string('reporter_phone', 50)->nullable();
            $table->foreignUuid('reporter_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Lifecycle — status transitions: open → acknowledged → closed
            $table->string('status', 20)->default('open');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignUuid('acknowledged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('admin_notes')->nullable();

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::table('equipment_issue_reports', function (Blueprint $table): void {
            $table->index(['equipment_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_issue_reports');
    }
};
