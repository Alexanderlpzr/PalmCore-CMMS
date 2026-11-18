<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El permiso oil-tanks.record —medir los tanques de aceite cada día— para el rol
 * «proceso», que mide en planta, y para «direccion-administrativa». Proceso no recibe
 * nada más del módulo comercial: ni ventas ni cartera. Pedido 2026-11-18. Idempotente.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $roles = ['proceso', 'direccion-administrativa'];

    public function up(): void
    {
        $permission = Permission::findOrCreate('oil-tanks.record', 'web');

        Tenant::query()->each(function (Tenant $tenant) use ($permission): void {
            setPermissionsTeamId($tenant->id);

            foreach ($this->roles as $roleName) {
                $role = Role::query()->where('name', $roleName)->where('team_id', $tenant->id)->first();

                if ($role !== null) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permission->id,
                        'role_id' => $role->id,
                    ]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionIds = Permission::query()->where('name', 'oil-tanks.record')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        Permission::query()->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
