<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El módulo comercial en cada empresa: los permisos commercial.view (ver clientes, ofertas
 * y despachos) y commercial.manage (registrarlos), y el rol «direccion-administrativa»,
 * que lleva los dos. Pedido por la Directora Administrativa de El Pajuil el 2026-11-17.
 *
 * El administrador general no los recibe: las ventas y la cartera son de la dirección,
 * como la nómina es de talento humano. Fronda asigna el rol desde el panel de plataforma.
 * Idempotente: los seeders no corren en deploy.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['commercial.view', 'commercial.manage'];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $permissionIds = Permission::query()->whereIn('name', $this->permissions)->where('guard_name', 'web')->pluck('id');

        Tenant::query()->each(function (Tenant $tenant) use ($permissionIds): void {
            setPermissionsTeamId($tenant->id);

            $role = Role::firstOrCreate(['name' => 'direccion-administrativa', 'guard_name' => 'web', 'team_id' => $tenant->id]);

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
        $roleIds = Role::query()->where('name', 'direccion-administrativa')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        $permissionIds = Permission::query()->whereIn('name', $this->permissions)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        Permission::query()->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
