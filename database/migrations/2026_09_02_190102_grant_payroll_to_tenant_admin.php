<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El administrador del tenant pasa a ver y administrar la nómina.
 *
 * Tres migraciones atrás estos permisos se dejaron fuera de `administrador-general` a
 * propósito, con el argumento de que el ingeniero de mantenimiento tiene derecho a ver
 * los equipos de su planta pero no el salario del Director de Planta. Se revierte por
 * decisión de la empresa, y conviene que quede escrito qué implica: **el administrador
 * general ve los sueldos de todos**. `employee-salaries.view` va incluido.
 *
 * El razonamiento de la empresa es razonable para su tamaño: en una extractora de
 * cuarenta y ocho personas, el administrador general y quien lleva la nómina suelen ser
 * la misma, y un rol al que el administrador no llega obliga a mantener dos cuentas para
 * una sola persona.
 *
 * `talento-humano` no desaparece y ahora sirve para lo contrario de lo que servía: dárselo
 * a alguien de RRHH que deba ver la nómina pero **no** los equipos, las órdenes ni el
 * inventario. Si algún día se quiere volver a aislar la nómina, esta migración es el único
 * sitio que hay que revertir.
 *
 * Idempotente: los seeders no corren en deploy.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissionNames = [
        'employees.view',
        'employees.create',
        'employees.update',
        'employees.delete',
        'employee-salaries.view',
        'employee-qr.view',
        'employee-qr.create',
        'employee-qr.update',
        'attendance.view',
        'attendance.record',
        'attendance.confirm',
        'payroll-runs.view',
        'payroll-runs.manage',
        'payroll-runs.close',
        'employee-novelties.view',
        'employee-novelties.manage',
        'payroll-parameters.view',
        'payroll-parameters.manage',
        'payroll-concepts.view',
        'payroll-concepts.manage',
        'holidays.view',
        'holidays.manage',
    ];

    public function up(): void
    {
        $roleIds = Role::query()->where('name', 'administrador-general')->pluck('id');

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
        $roleIds = Role::query()->where('name', 'administrador-general')->pluck('id');

        $permissionIds = Permission::query()
            ->whereIn('name', $this->permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        // Solo se le quitan al administrador: los permisos siguen existiendo y
        // `talento-humano` los conserva.
        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
