<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // equipment — FK columns without indexes; PostgreSQL does not auto-create them.
        Schema::table('equipment', function (Blueprint $table): void {
            if (! $this->indexExists('equipment', 'equipment_category_id_index')) {
                $table->index('category_id');
            }
            if (! $this->indexExists('equipment', 'equipment_manufacturer_id_index')) {
                $table->index('manufacturer_id');
            }
            if (! $this->indexExists('equipment', 'equipment_supplier_id_index')) {
                $table->index('supplier_id');
            }
            if (! $this->indexExists('equipment', 'equipment_parent_equipment_id_index')) {
                $table->index('parent_equipment_id');
            }
        });

        // work_orders — user FK columns used in filters and user-centric views
        Schema::table('work_orders', function (Blueprint $table): void {
            if (! $this->indexExists('work_orders', 'work_orders_assigned_supervisor_index')) {
                $table->index('assigned_supervisor');
            }
            if (! $this->indexExists('work_orders', 'work_orders_completed_by_index')) {
                $table->index('completed_by');
            }
            if (! $this->indexExists('work_orders', 'work_orders_verified_by_index')) {
                $table->index('verified_by');
            }
            if (! $this->indexExists('work_orders', 'work_orders_plant_id_index')) {
                $table->index('plant_id');
            }
            if (! $this->indexExists('work_orders', 'work_orders_area_id_index')) {
                $table->index('area_id');
            }
        });

        // maintenance_requests — reviewer/approver FKs used in workflow queries
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            if (! $this->indexExists('maintenance_requests', 'maintenance_requests_created_by_index')) {
                $table->index('created_by');
            }
            if (! $this->indexExists('maintenance_requests', 'maintenance_requests_assigned_reviewer_index')) {
                $table->index('assigned_reviewer');
            }
            if (! $this->indexExists('maintenance_requests', 'maintenance_requests_approved_by_index')) {
                $table->index('approved_by');
            }
            if (! $this->indexExists('maintenance_requests', 'maintenance_requests_rejected_by_index')) {
                $table->index('rejected_by');
            }
        });

        // work_order_time_logs — user_id used for technician KPI queries
        Schema::table('work_order_time_logs', function (Blueprint $table): void {
            if (! $this->indexExists('work_order_time_logs', 'work_order_time_logs_user_id_index')) {
                $table->index('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table): void {
            $table->dropIndexIfExists('equipment_category_id_index');
            $table->dropIndexIfExists('equipment_manufacturer_id_index');
            $table->dropIndexIfExists('equipment_supplier_id_index');
            $table->dropIndexIfExists('equipment_parent_equipment_id_index');
        });

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropIndexIfExists('work_orders_assigned_supervisor_index');
            $table->dropIndexIfExists('work_orders_completed_by_index');
            $table->dropIndexIfExists('work_orders_verified_by_index');
            $table->dropIndexIfExists('work_orders_plant_id_index');
            $table->dropIndexIfExists('work_orders_area_id_index');
        });

        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $table->dropIndexIfExists('maintenance_requests_created_by_index');
            $table->dropIndexIfExists('maintenance_requests_assigned_reviewer_index');
            $table->dropIndexIfExists('maintenance_requests_approved_by_index');
            $table->dropIndexIfExists('maintenance_requests_rejected_by_index');
        });

        Schema::table('work_order_time_logs', function (Blueprint $table): void {
            $table->dropIndexIfExists('work_order_time_logs_user_id_index');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select(
            'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $index]
        ))->isNotEmpty();
    }
};
