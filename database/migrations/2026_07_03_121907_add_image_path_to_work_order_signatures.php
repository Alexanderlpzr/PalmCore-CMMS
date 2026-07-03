<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_order_signatures', function (Blueprint $table) {
            // The signature image was previously captured on the client (watermarked
            // canvas PNG) but only ever uploaded as a generic, unlinked work-order
            // attachment — the signature record itself never stored a reference to it,
            // so the PDF's signature box always rendered empty. This column is the fix.
            $table->string('image_path', 500)->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_signatures', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
