<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace the standard unique constraint on equipment_id with a partial unique index
     * (WHERE deleted_at IS NULL). This allows soft-deleted records to coexist with a new
     * active QR code for the same equipment — required for QR regeneration to work.
     */
    public function up(): void
    {
        // Drop the standard unique constraint created by foreignUuid(...)->unique()
        DB::statement('ALTER TABLE equipment_qr_codes DROP CONSTRAINT IF EXISTS equipment_qr_codes_equipment_id_unique');

        // Partial unique: only one active (non-deleted) QR per equipment at a time
        DB::statement(
            'CREATE UNIQUE INDEX equipment_qr_codes_equipment_id_active_unique
             ON equipment_qr_codes (equipment_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS equipment_qr_codes_equipment_id_active_unique');
        DB::statement('ALTER TABLE equipment_qr_codes ADD CONSTRAINT equipment_qr_codes_equipment_id_unique UNIQUE (equipment_id)');
    }
};
