<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El almacén crea sus almacenes: sin uno no puede levantar repuestos ni registrar nada,
 * y el rol solo podía verlos y editarlos. Idempotente: los seeders no corren en deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('warehouses.create', 'web');

        Role::query()->where('name', 'almacen')->pluck('id')->each(function (string|int $roleId) use ($permission): void {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permission->id,
                'role_id' => $roleId,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = Permission::query()->where('name', 'warehouses.create')->where('guard_name', 'web')->value('id');
        $roleIds = Role::query()->where('name', 'almacen')->pluck('id');

        DB::table('role_has_permissions')->where('permission_id', $permissionId)->whereIn('role_id', $roleIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
