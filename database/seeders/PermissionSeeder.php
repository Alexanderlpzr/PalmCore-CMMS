<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Permissions are global (no team_id) — they represent the system's action catalog.
     * Roles are per-tenant and reference these global permissions.
     *
     * @var list<string>
     */
    private array $permissions = [
        // Users
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.restore',

        // Tenants
        'tenants.view',
        'tenants.create',
        'tenants.update',
        'tenants.delete',

        // Plants
        'plants.view',
        'plants.create',
        'plants.update',
        'plants.delete',

        // Areas
        'areas.view',
        'areas.create',
        'areas.update',
        'areas.delete',

        // Roles
        'roles.view',
        'roles.assign',
        'roles.revoke',

        // User profiles
        'user-profiles.view',
        'user-profiles.update',

        // Audit & permissions
        'audit-log.view',
        'permissions.manage',

        // Equipment Categories
        'equipment-categories.view',
        'equipment-categories.create',
        'equipment-categories.update',
        'equipment-categories.delete',

        // Manufacturers
        'manufacturers.view',
        'manufacturers.create',
        'manufacturers.update',
        'manufacturers.delete',

        // Suppliers
        'suppliers.view',
        'suppliers.create',
        'suppliers.update',
        'suppliers.delete',

        // Equipment
        'equipment.view',
        'equipment.create',
        'equipment.update',
        'equipment.delete',

        // Equipment Documents
        'equipment-documents.view',
        'equipment-documents.create',
        'equipment-documents.update',
        'equipment-documents.delete',

        // Equipment Photos
        'equipment-photos.view',
        'equipment-photos.create',
        'equipment-photos.update',
        'equipment-photos.delete',

        // Equipment QR Codes
        'equipment-qr.view',
        'equipment-qr.create',
        'equipment-qr.update',

        // Issue Reports (viewed/managed by supervisors+)
        'issue-reports.view',
        'issue-reports.acknowledge',

        // Maintenance Requests
        'maintenance-requests.view',
        'maintenance-requests.create',
        'maintenance-requests.update',
        'maintenance-requests.delete',
        'maintenance-requests.approve',
        'maintenance-requests.review',
        'maintenance-requests.convert',

        // Maintenance Request Comments
        'maintenance-request-comments.view',
        'maintenance-request-comments.create',

        // Maintenance Request Attachments
        'maintenance-request-attachments.create',

        // Work Orders
        'work-orders.view',
        'work-orders.create',
        'work-orders.update',
        'work-orders.delete',
        'work-orders.plan',
        'work-orders.execute',
        'work-orders.verify',
        'work-orders.close',

        // Work Order sub-records
        'work-order-comments.view',
        'work-order-comments.create',
        'work-order-time-logs.create',
        'work-order-parts.create',
        'work-order-signatures.create',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
