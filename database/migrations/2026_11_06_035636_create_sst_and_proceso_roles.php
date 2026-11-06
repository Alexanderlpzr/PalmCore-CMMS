<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los hallazgos de SST en cada empresa: los permisos, el rol «sst» —quien reporta y
 * verifica— y el rol «proceso» —producción, que recibe los hallazgos y corrige los
 * suyos—. El administrador general los maneja todos y mantenimiento ejecuta su parte.
 *
 * Solo crea los roles: quién los lleva lo asigna Fronda desde el panel de plataforma.
 * Idempotente: los seeders no corren en deploy. Pedido por El Pajuil el 2026-11-05.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $grants = [
        'sst' => ['sst-findings.view', 'sst-findings.manage', 'sst-findings.execute'],
        'proceso' => ['sst-findings.view', 'sst-findings.execute'],
        'administrador-general' => ['sst-findings.view', 'sst-findings.manage', 'sst-findings.execute'],
        'mantenimiento' => ['sst-findings.view', 'sst-findings.execute'],
    ];

    public function up(): void
    {
        $names = ['sst-findings.view', 'sst-findings.manage', 'sst-findings.execute'];

        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $permissionIds = Permission::query()->whereIn('name', $names)->where('guard_name', 'web')->pluck('id', 'name');

        Tenant::query()->each(function (Tenant $tenant) use ($permissionIds): void {
            setPermissionsTeamId($tenant->id);

            foreach ($this->grants as $roleName => $permissions) {
                $role = in_array($roleName, ['sst', 'proceso'], true)
                    ? Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'team_id' => $tenant->id])
                    : Role::query()->where('name', $roleName)->where('team_id', $tenant->id)->first();

                if ($role === null) {
                    continue;
                }

                foreach ($permissions as $permission) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionIds[$permission],
                        'role_id' => $role->id,
                    ]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()->whereIn('name', ['sst', 'proceso'])->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        $permissionIds = Permission::query()->where('name', 'like', 'sst-findings.%')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        Permission::query()->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
