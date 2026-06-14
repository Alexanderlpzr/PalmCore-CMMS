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
        Schema::create('automation_rule_executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_id')->references('id')->on('automation_rules');
            $table->string('entity_type', 50);
            $table->uuid('entity_id');        // no FK — polymorphic, entity may be deleted
            $table->string('action_taken', 80);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('executed_at');
            $table->timestampTz('created_at');

            // PostgreSQL is the last line of defence against duplicate executions
            $table->unique(['rule_id', 'entity_type', 'entity_id', 'action_taken']);

            $table->index('rule_id');
            $table->index(['entity_type', 'entity_id']);
            $table->index('executed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rule_executions');
    }
};
