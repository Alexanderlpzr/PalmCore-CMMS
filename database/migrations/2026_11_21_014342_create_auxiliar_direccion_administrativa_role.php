<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * La auxiliar de la Directora Administrativa: el permiso commercial-invoices.manage y su
 * rol. Ve los despachos y lleva la factura de cada uno —número, fecha, valor y estado—;
 * no toca pesos, clientes ni ofertas, y no entra al cuadro cruce ni al plan. Pedido
 * 2026-11-20. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('commercial-invoices.manage', 'web');

        Tenant::query()->each(function (Tenant $tenant) use ($permission): void {
            setPermissionsTeamId($tenant->id);

            $role = Role::firstOrCreate(['name' => 'auxiliar-direccion-administrativa', 'guard_name' => 'web', 'team_id' => $tenant->id]);

            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permission->id,
                'role_id' => $role->id,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()->where('name', 'auxiliar-direccion-administrativa')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        $permissionIds = Permission::query()->where('name', 'commercial-invoices.manage')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        Permission::query()->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
