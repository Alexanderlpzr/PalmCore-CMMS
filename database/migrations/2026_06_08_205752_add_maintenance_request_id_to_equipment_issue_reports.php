<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_issue_reports', function (Blueprint $table) {
            $table->foreignUuid('maintenance_request_id')
                ->nullable()
                ->after('status')
                ->constrained('maintenance_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_issue_reports', function (Blueprint $table) {
            $table->dropForeign(['maintenance_request_id']);
            $table->dropColumn('maintenance_request_id');
        });
    }
};
