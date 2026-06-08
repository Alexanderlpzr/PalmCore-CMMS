<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_qr_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // One QR per equipment, unique constraint enforces this
            $table->foreignUuid('equipment_id')
                ->unique()
                ->constrained('equipment')
                ->cascadeOnDelete();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // UUID v4 (random) — NOT v7 — to prevent enumeration via timing
            $table->uuid('qr_token')->unique();

            $table->string('qr_image_path', 500)->nullable();
            $table->boolean('is_active')->default(true);

            // Lifecycle tracking
            $table->timestampTz('generated_at')->nullable();
            $table->timestampTz('last_scanned_at')->nullable();
            $table->unsignedBigInteger('scan_count')->default(0);

            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Index for public token lookups (hot path: every QR scan)
        // The UNIQUE constraint already creates an index on qr_token,
        // but we add a partial index for active-only lookups.
        \DB::statement(
            'CREATE INDEX equipment_qr_codes_active_token_idx
             ON equipment_qr_codes (qr_token)
             WHERE deleted_at IS NULL AND is_active = true'
        );

        Schema::table('equipment_qr_codes', function (Blueprint $table): void {
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_qr_codes');
    }
};
