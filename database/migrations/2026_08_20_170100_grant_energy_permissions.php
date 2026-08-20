<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos del módulo de energía, para los tenants que ya existen.
 *
 * Están declarados en `PermissionSeeder` y en la matriz de `TenantRolesSeeder`, pero los
 * seeders no corren en deploy. Sin esta migración, Filament evalúa la policy y Spatie
 * lanza excepción porque el permiso ni siquiera existe: la pantalla revienta en vez de
 * ocultarse. Idempotente.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissionNames = ['energy.view', 'energy.manage'];

    /** @var array<int, string> */
    private array $roleNames = ['administrador-general'];

    public function up(): void
    {
        $roleIds = Role::query()->whereIn('name', $this->roleNames)->pluck('id');

        foreach ($this->permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');

            $permissionId = Permission::query()
                ->where('name', $permissionName)
                ->where('guard_name', 'web')
                ->value('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()->whereIn('name', $this->roleNames)->pluck('id');

        $permissionIds = Permission::query()
            ->whereIn('name', $this->permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
