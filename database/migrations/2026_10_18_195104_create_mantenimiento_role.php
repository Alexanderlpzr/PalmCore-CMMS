<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol de mantenimiento en cada empresa: equipos, órdenes, planes, paros, horómetros,
 * producción, energía, presupuesto e indicadores. Sin inventario —es del almacén, que
 * trabaja aparte— y sin administrar usuarios ni roles, que hace Fronda. Las órdenes de
 * trabajo siguen sacando repuestos del almacén.
 *
 * Solo crea el rol: quién lo lleva lo asigna Fronda desde el panel de plataforma. Así no
 * se le cambia el acceso a nadie por desplegar. Idempotente: los seeders no corren en
 * deploy.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = [
        'plants.view', 'plants.create', 'plants.update', 'plants.delete',
        'areas.view', 'areas.create', 'areas.update', 'areas.delete',
        'audit-log.view',
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
        'announcements.view', 'announcements.create', 'announcements.update', 'announcements.delete',
        'carousel-slides.view', 'carousel-slides.create', 'carousel-slides.update', 'carousel-slides.delete',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $permissionIds = Permission::query()
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        Tenant::query()->each(function (Tenant $tenant) use ($permissionIds): void {
            setPermissionsTeamId($tenant->id);

            $role = Role::firstOrCreate([
                'name' => 'mantenimiento',
                'guard_name' => 'web',
                'team_id' => $tenant->id,
            ]);

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $role->id,
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()->where('name', 'mantenimiento')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
