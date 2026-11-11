<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol «ambiental» en cada empresa: los mismos permisos que SST sobre los hallazgos
 * —verlos, reportarlos, verificarlos y ejecutar la parte de SST—, para la cuenta de
 * gestión ambiental. Pedido por El Pajuil el 2026-11-10.
 *
 * Solo crea el rol: quién lo lleva lo asigna Fronda desde el panel de plataforma.
 * Idempotente.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['sst-findings.view', 'sst-findings.manage', 'sst-findings.execute'];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $permissionIds = Permission::query()->whereIn('name', $this->permissions)->where('guard_name', 'web')->pluck('id');

        Tenant::query()->each(function (Tenant $tenant) use ($permissionIds): void {
            setPermissionsTeamId($tenant->id);

            $role = Role::firstOrCreate(['name' => 'ambiental', 'guard_name' => 'web', 'team_id' => $tenant->id]);

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
        $roleIds = Role::query()->where('name', 'ambiental')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
