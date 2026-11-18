<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El tablero de Dirección: el permiso management.view y el rol «gerencia-general», de
 * solo consulta, para el gerente o la junta. Ve los totales que cruzan las áreas —ventas,
 * costo de personal por área, mantenimiento, planta, SST— sin sueldos individuales ni
 * permiso para cambiar nada. Pedido 2026-11-18.
 *
 * No se llama «gerencia» porque ese era uno de los nueve roles retirados en agosto
 * (2026_08_01_064130_drop_retired_tenant_roles). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('management.view', 'web');

        Tenant::query()->each(function (Tenant $tenant) use ($permission): void {
            setPermissionsTeamId($tenant->id);

            $role = Role::firstOrCreate(['name' => 'gerencia-general', 'guard_name' => 'web', 'team_id' => $tenant->id]);

            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permission->id,
                'role_id' => $role->id,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()->where('name', 'gerencia-general')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        $permissionIds = Permission::query()->where('name', 'management.view')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        Permission::query()->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
