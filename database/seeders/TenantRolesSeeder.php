<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class TenantRolesSeeder extends Seeder
{
    /**
     * Role → permission matrix (complete, all modules).
     * All permissions are global; roles are scoped per tenant via team_id.
     *
     * @var array<string, list<string>>
     */
    private array $rolePermissions = [

        // Full system control within the tenant.
        'administrador-general' => [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.restore',
            'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
            'plants.view', 'plants.create', 'plants.update', 'plants.delete',
            'areas.view', 'areas.create', 'areas.update', 'areas.delete',
            'roles.view', 'roles.assign', 'roles.revoke',
            'user-profiles.view', 'user-profiles.update',
            'audit-log.view', 'permissions.manage',
            'equipment-categories.view', 'equipment-categories.create', 'equipment-categories.update', 'equipment-categories.delete',
            'manufacturers.view', 'manufacturers.create', 'manufacturers.update', 'manufacturers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete',
            'equipment-documents.view', 'equipment-documents.create', 'equipment-documents.update', 'equipment-documents.delete',
            'equipment-photos.view', 'equipment-photos.create', 'equipment-photos.update', 'equipment-photos.delete',
            'equipment-qr.view', 'equipment-qr.create', 'equipment-qr.update',
            'issue-reports.view', 'issue-reports.acknowledge',
            'maintenance-requests.view', 'maintenance-requests.create', 'maintenance-requests.update', 'maintenance-requests.delete',
            'maintenance-requests.approve', 'maintenance-requests.review', 'maintenance-requests.convert',
            'maintenance-request-comments.view', 'maintenance-request-comments.create',
            'maintenance-request-attachments.create',
            'work-orders.view', 'work-orders.create', 'work-orders.update', 'work-orders.delete',
            'work-orders.plan', 'work-orders.execute', 'work-orders.verify', 'work-orders.close',
            'work-order-comments.view', 'work-order-comments.create',
            'work-order-time-logs.create', 'work-order-parts.create', 'work-order-signatures.create',
            'maintenance-plans.view', 'maintenance-plans.create', 'maintenance-plans.update', 'maintenance-plans.delete', 'maintenance-plans.activate',
            'maintenance-plan-tasks.create', 'maintenance-plan-tasks.update',
            'maintenance-checklist-items.create', 'maintenance-plan-attachments.create',
            'equipment-meter-readings.create',
            'spare-parts.view', 'spare-parts.create', 'spare-parts.update', 'spare-parts.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'inventory.view', 'inventory.entry', 'inventory.exit', 'inventory.adjust', 'inventory.transfer',
            'announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete',
            'carousel-slides.view', 'carousel-slides.create', 'carousel-slides.update', 'carousel-slides.delete',
        ],

        // Read-only executive oversight across all modules.
        'gerencia' => [
            'users.view',
            'tenants.view',
            'plants.view',
            'areas.view',
            'roles.view',
            'user-profiles.view',
            'audit-log.view',
            'equipment-categories.view',
            'manufacturers.view',
            'suppliers.view',
            'equipment.view',
            'equipment-documents.view',
            'equipment-photos.view',
            'equipment-qr.view',
            'issue-reports.view',
            'maintenance-requests.view',
            'maintenance-request-comments.view',
            'work-orders.view',
            'work-order-comments.view',
            'maintenance-plans.view',
            'spare-parts.view',
            'warehouses.view',
            'inventory.view',
        ],

        // Manages plant operations, approves and closes maintenance work.
        'plant-manager' => [
            'users.view',
            'plants.view', 'plants.create', 'plants.update',
            'areas.view', 'areas.create', 'areas.update',
            'user-profiles.view',
            'equipment-categories.view',
            'manufacturers.view',
            'suppliers.view',
            'equipment.view',
            'equipment-documents.view',
            'equipment-photos.view',
            'equipment-qr.view',
            'issue-reports.view', 'issue-reports.acknowledge',
            'maintenance-requests.view', 'maintenance-requests.approve', 'maintenance-requests.review', 'maintenance-requests.convert',
            'maintenance-request-comments.view', 'maintenance-request-comments.create',
            'work-orders.view', 'work-orders.create', 'work-orders.update', 'work-orders.plan', 'work-orders.close',
            'work-order-comments.view', 'work-order-comments.create',
            'maintenance-plans.view', 'maintenance-plans.create', 'maintenance-plans.update', 'maintenance-plans.activate',
            'spare-parts.view',
            'warehouses.view',
            'inventory.view',
        ],

        // Technical lead: full lifecycle of equipment and maintenance.
        'ingeniero-mantenimiento' => [
            'users.view',
            'plants.view',
            'areas.view',
            'user-profiles.view',
            'equipment-categories.view', 'equipment-categories.create', 'equipment-categories.update',
            'manufacturers.view', 'manufacturers.create', 'manufacturers.update',
            'equipment.view', 'equipment.create', 'equipment.update',
            'equipment-documents.view', 'equipment-documents.create', 'equipment-documents.update',
            'equipment-photos.view', 'equipment-photos.create', 'equipment-photos.update',
            'equipment-qr.view', 'equipment-qr.create', 'equipment-qr.update',
            'issue-reports.view', 'issue-reports.acknowledge',
            'maintenance-requests.view', 'maintenance-requests.create', 'maintenance-requests.update',
            'maintenance-requests.approve', 'maintenance-requests.review', 'maintenance-requests.convert',
            'maintenance-request-comments.view', 'maintenance-request-comments.create',
            'maintenance-request-attachments.create',
            'work-orders.view', 'work-orders.create', 'work-orders.update',
            'work-orders.plan', 'work-orders.execute', 'work-orders.verify', 'work-orders.close',
            'work-order-comments.view', 'work-order-comments.create',
            'work-order-time-logs.create', 'work-order-parts.create', 'work-order-signatures.create',
            'maintenance-plans.view', 'maintenance-plans.create', 'maintenance-plans.update', 'maintenance-plans.delete', 'maintenance-plans.activate',
            'maintenance-plan-tasks.create', 'maintenance-plan-tasks.update',
            'maintenance-checklist-items.create', 'maintenance-plan-attachments.create',
            'equipment-meter-readings.create',
            'spare-parts.view',
            'warehouses.view',
            'inventory.view',
        ],

        // Supervisory oversight: approves requests, verifies and closes work orders.
        'supervisor' => [
            'users.view',
            'plants.view',
            'areas.view',
            'equipment.view',
            'equipment-documents.view',
            'equipment-photos.view',
            'equipment-qr.view',
            'issue-reports.view', 'issue-reports.acknowledge',
            'maintenance-requests.view', 'maintenance-requests.create', 'maintenance-requests.update',
            'maintenance-requests.approve', 'maintenance-requests.review',
            'maintenance-request-comments.view', 'maintenance-request-comments.create',
            'maintenance-request-attachments.create',
            'work-orders.view', 'work-orders.create', 'work-orders.update', 'work-orders.verify', 'work-orders.close',
            'work-order-comments.view', 'work-order-comments.create',
            'maintenance-plans.view', 'maintenance-plans.activate',
            'spare-parts.view',
            'warehouses.view',
            'inventory.view',
        ],

        // Field technician: executes work orders, logs time, consumes parts.
        'tecnico' => [
            'areas.view',
            'user-profiles.view',
            'equipment.view',
            'equipment-photos.view',
            'equipment-qr.view',
            'issue-reports.view',
            'maintenance-requests.view', 'maintenance-requests.create',
            'maintenance-request-comments.view', 'maintenance-request-comments.create',
            'maintenance-request-attachments.create',
            'work-orders.view', 'work-orders.execute',
            'work-order-comments.view', 'work-order-comments.create',
            'work-order-time-logs.create', 'work-order-parts.create', 'work-order-signatures.create',
            'maintenance-plans.view',
            'equipment-meter-readings.create',
            'spare-parts.view',
            'inventory.view',
        ],

        // Warehouse staff: manages stock, processes part movements.
        'almacenista' => [
            'plants.view',
            'areas.view',
            'equipment.view',
            'spare-parts.view', 'spare-parts.create', 'spare-parts.update',
            'warehouses.view', 'warehouses.create', 'warehouses.update',
            'inventory.view', 'inventory.entry', 'inventory.exit', 'inventory.adjust', 'inventory.transfer',
            'work-orders.view',
            'work-order-parts.create',
        ],

        // Purchasing: manages suppliers, manufacturers and procurement catalogue.
        'compras' => [
            'plants.view',
            'areas.view',
            'suppliers.view', 'suppliers.create', 'suppliers.update',
            'manufacturers.view', 'manufacturers.create', 'manufacturers.update',
            'spare-parts.view', 'spare-parts.create', 'spare-parts.update',
            'warehouses.view',
            'inventory.view',
        ],

        // Operator: reports issues and creates maintenance requests.
        'operario' => [
            'areas.view',
            'equipment.view',
            'equipment-qr.view',
            'issue-reports.view',
            'maintenance-requests.view', 'maintenance-requests.create',
            'maintenance-request-comments.view', 'maintenance-request-comments.create',
            'maintenance-request-attachments.create',
            'work-orders.view',
        ],
    ];

    public function run(Tenant $tenant): void
    {
        // Scope all Spatie role queries and creations to this tenant.
        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'team_id' => $tenant->id,
            ]);

            $role->syncPermissions($permissions);
        }
    }
}
