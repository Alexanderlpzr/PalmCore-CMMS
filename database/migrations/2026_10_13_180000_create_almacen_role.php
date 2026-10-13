<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol de almacén en cada empresa: repuestos, existencias, movimientos, órdenes de compra
 * y proveedores, y nada de equipos, órdenes de trabajo ni personal. Es el de la cuenta de
 * almacén, que abre el panel en su tablero.
 *
 * Idempotente: los seeders no corren en deploy.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = [
        'spare-parts.view', 'spare-parts.create', 'spare-parts.update',
        'warehouses.view', 'warehouses.update',
        'inventory.view', 'inventory.entry', 'inventory.exit', 'inventory.adjust', 'inventory.transfer',
        'suppliers.view', 'suppliers.create', 'suppliers.update',
        'manufacturers.view',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        Tenant::query()->each(function (Tenant $tenant): void {
            setPermissionsTeamId($tenant->id);

            $role = Role::firstOrCreate([
                'name' => 'almacen',
                'guard_name' => 'web',
                'team_id' => $tenant->id,
            ]);

            foreach ($this->permissions as $permissionName) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => Permission::query()->where('name', $permissionName)->where('guard_name', 'web')->value('id'),
                    'role_id' => $role->id,
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()->where('name', 'almacen')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
