<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');

            $table->string('signature_type', 30);  // WorkOrderSignatureType enum
            $table->timestampTz('signed_at', 0);
            $table->text('notes')->nullable();

            $table->timestampsTz(0);
            // No soft deletes — signatures are permanent audit records

            $table->unique(['work_order_id', 'signature_type']);  // one signature per type per WO
            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_signatures');
    }
};
