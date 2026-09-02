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

            /*
             * Nómina. Al principio se dejó fuera a propósito, con el argumento de que un
             * ingeniero de mantenimiento tiene derecho a ver los equipos de su planta pero
             * no el salario del Director de Planta.
             *
             * Se revierte por decisión de la empresa: en una extractora de este tamaño el
             * administrador general y quien lleva la nómina suelen ser la misma persona, y
             * un rol al que el administrador no llega obliga a mantener dos cuentas para
             * una sola persona. `employee-salaries.view` va incluido, así que el
             * administrador ve los sueldos: es el precio de la decisión y conviene tenerlo
             * escrito.
             *
             * El rol `talento-humano` sigue existiendo y sirve para lo contrario: dárselo a
             * alguien de RRHH que NO deba ver equipos, órdenes ni inventario.
             */
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'employee-salaries.view',
            'employee-qr.view', 'employee-qr.create', 'employee-qr.update',
            'attendance.view', 'attendance.record', 'attendance.confirm',
            'payroll-runs.view', 'payroll-runs.manage', 'payroll-runs.close',
            'employee-novelties.view', 'employee-novelties.manage',
            'payroll-parameters.view', 'payroll-parameters.manage',
            'payroll-concepts.view', 'payroll-concepts.manage',
            'holidays.view', 'holidays.manage',
        ],

        /*
         * El rol para quien lleva la nómina y nada más: ve personal, horas, parámetros y
         * liquidaciones, pero ningún equipo ni orden de trabajo. No marca en portería —esa
         * es la única separación de funciones que queda en pie— porque quien liquida las
         * horas no debería ser quien las registra en la puerta.
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
