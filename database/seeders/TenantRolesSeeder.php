<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRolesSeeder extends Seeder
{
    /**
     * Role → permission matrix for Sprint 1 (IAM module).
     * All permissions are global; roles are scoped per tenant via team_id.
     *
     * @var array<string, list<string>>
     */
    private array $rolePermissions = [
        'administrador-general' => [
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.restore',
            'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
            'plants.view', 'plants.create', 'plants.update', 'plants.delete',
            'areas.view', 'areas.create', 'areas.update', 'areas.delete',
            'roles.view', 'roles.assign', 'roles.revoke',
            'user-profiles.view', 'user-profiles.update',
            'audit-log.view', 'permissions.manage',
        ],
        'gerencia' => [
            'users.view',
            'tenants.view',
            'plants.view',
            'areas.view',
            'roles.view',
            'user-profiles.view',
            'audit-log.view',
        ],
        'plant-manager' => [
            'users.view',
            'plants.view', 'plants.create', 'plants.update',
            'areas.view', 'areas.create', 'areas.update',
            'user-profiles.view',
        ],
        'ingeniero-mantenimiento' => [
            'users.view',
            'plants.view',
            'areas.view',
            'user-profiles.view',
        ],
        'supervisor' => [
            'users.view',
            'plants.view',
            'areas.view',
        ],
        'tecnico' => [
            'areas.view',
            'user-profiles.view',
        ],
        'almacenista' => [
            'plants.view',
            'areas.view',
        ],
        'compras' => [
            'plants.view',
            'areas.view',
        ],
        'operario' => [
            'areas.view',
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
