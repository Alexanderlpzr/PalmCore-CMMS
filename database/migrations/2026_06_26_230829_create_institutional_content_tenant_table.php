<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_content_tenant', function (Blueprint $table): void {
            $table->foreignUuid('institutional_content_id')->constrained('institutional_contents')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->primary(['institutional_content_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_content_tenant');
    }
};
