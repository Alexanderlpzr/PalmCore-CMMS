<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos de la liquidación, para los tenants que ya existen.
 *
 * `payroll-runs.close` va aparte de `payroll-runs.manage` porque son responsabilidades
 * distintas: liquidar se puede repetir sin consecuencias mientras la nómina esté en
 * borrador, y cerrar es el punto tras el cual se emiten los desprendibles y se aportan las
 * cifras. Hoy los dos los tiene talento humano, pero separarlos deja la puerta abierta a
 * que quien prepara la nómina no sea quien la aprueba, sin tener que migrar nada después.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissionNames = [
        'payroll-runs.view',
        'payroll-runs.manage',
        'payroll-runs.close',
        'employee-novelties.view',
        'employee-novelties.manage',
    ];

    /** @var array<int, string> */
    private array $roleNames = ['talento-humano'];

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
        $permissionIds = Permission::query()
            ->whereIn('name', $this->permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        Permission::query()->whereIn('id', $permissionIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
