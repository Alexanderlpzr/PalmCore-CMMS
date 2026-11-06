<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los hallazgos de SST: el «Cuadro de hallazgos SG-SST / Ambiental» que SST llevaba en
 * Excel. Cada hallazgo tiene dónde está, su prioridad —que fija el plazo—, las fotos del
 * antes y el después, y una parte por cada área responsable. La de mantenimiento viaja
 * como reporte de novedad, que se vuelve OT por el flujo de siempre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sst_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('reported_on');
            $table->text('description')->nullable();
            $table->foreignUuid('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignUuid('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->string('location_detail', 255)->nullable();
            $table->string('priority', 10);
            $table->text('action_plan')->nullable();
            $table->date('due_on');
            $table->string('status', 20)->default('pendiente');
            $table->string('photo_before_path')->nullable();
            $table->string('photo_after_path')->nullable();
            $table->date('executed_on')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->timestampTz('overdue_notified_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'due_on']);
        });

        Schema::create('sst_finding_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('sst_finding_id')->constrained('sst_findings')->cascadeOnDelete();
            $table->string('area', 20);
            $table->string('status', 20)->default('pendiente');
            $table->timestampTz('started_at')->nullable();
            $table->date('executed_on')->nullable();
            $table->foreignUuid('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('issue_report_id')->nullable()->constrained('equipment_issue_reports')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['sst_finding_id', 'area']);
            $table->index('issue_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_finding_tasks');
        Schema::dropIfExists('sst_findings');
    }
};
