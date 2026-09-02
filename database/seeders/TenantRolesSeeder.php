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
     * A single role by design: the maintenance engineer administers his own
     * tenant, and we administer the platform through is_super_admin. The former
     * nine-role matrix (gerencia, plant-manager, ingeniero-mantenimiento,
     * supervisor, tecnico, almacenista, compras, operario) was retired — see the
     * migration that drops them from existing tenants.
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
            'contractors.view', 'contractors.create', 'contractors.update', 'contractors.delete',
            'equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete',
            'equipment-documents.view', 'equipment-documents.create', 'equipment-documents.update', 'equipment-documents.delete',
            'equipment-photos.view', 'equipment-photos.create', 'equipment-photos.update', 'equipment-photos.delete',
            'equipment-qr.view', 'equipment-qr.create', 'equipment-qr.update',
            'issue-reports.view', 'issue-reports.acknowledge', 'issue-reports.archive',
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
            'equipment-meter-readings.view', 'equipment-meter-readings.create',
            'downtime-events.view', 'downtime-events.create', 'downtime-events.update', 'downtime-events.confirm',
            'production-calendar.view', 'production-calendar.manage',
            'energy.view', 'energy.manage',
            'maintenance-budgets.view', 'maintenance-budgets.manage',
            'spare-parts.view', 'spare-parts.create', 'spare-parts.update', 'spare-parts.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'inventory.view', 'inventory.entry', 'inventory.exit', 'inventory.adjust', 'inventory.transfer',
            'announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete',
            'carousel-slides.view', 'carousel-slides.create', 'carousel-slides.update', 'carousel-slides.delete',
        ],

        /*
         * Nómina. Deliberadamente fuera de 'administrador-general', que es lo que rompe
         * el «un solo rol por diseño» de arriba.
         *
         * El motivo no es que hicieran falta más roles: es que hasta ahora todo lo que el
         * sistema guarda —equipos, órdenes, inventario— es información que el ingeniero
         * de mantenimiento tiene todo el derecho a ver en su planta. El salario del
         * Director de Planta no lo es. Si en una empresa la misma persona hace las dos
         * cosas, se le asignan los dos roles y queda el rastro de quién lo autorizó.
         */
        'talento-humano' => [
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'employee-salaries.view',
            'employee-qr.view', 'employee-qr.create', 'employee-qr.update',
            'attendance.view', 'attendance.confirm',
            'payroll-runs.view', 'payroll-runs.manage', 'payroll-runs.close',
            'employee-novelties.view', 'employee-novelties.manage',
            'payroll-parameters.view', 'payroll-parameters.manage',
            'payroll-concepts.view', 'payroll-concepts.manage',
            'holidays.view', 'holidays.manage',
        ],

        // Lo mínimo para operar la puerta: marcar y ver lo que marcó. Sin sueldos.
        'porteria' => [
            'employees.view',
            'attendance.view', 'attendance.record',
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
