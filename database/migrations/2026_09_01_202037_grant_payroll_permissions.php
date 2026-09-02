<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos de nómina y los dos roles nuevos, para los tenants que ya existen.
 *
 * Aquí se rompe deliberadamente el «un solo rol por diseño» que documenta
 * `TenantRolesSeeder`. La razón no es que hicieran falta más roles, sino que la nómina
 * es la primera información del sistema que el administrador del tenant **no** debe ver.
 * Hasta hoy `administrador-general` tiene absolutamente todos los permisos y eso estaba
 * bien: un ingeniero de mantenimiento que ve todos los equipos de su planta es lo
 * esperado. Un ingeniero de mantenimiento que ve el salario del Director de Planta no.
 *
 * Por eso ninguno de estos permisos entra en `administrador-general`. Si en una empresa
 * la misma persona hace las dos cosas, se le asigna el rol de talento humano además del
 * suyo, y queda el rastro de quién se lo asignó. La alternativa —dárselo a todos por
 * omisión y confiar en que nadie mire— no deja rastro de nada.
 *
 * `employee-salaries.view` se separa de `employees.view` a propósito: portería necesita
 * saber a quién acaba de escanear, y para eso le basta el nombre. El sueldo es otra
 * facultad y otro rol.
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
        'payroll-parameters.view',
        'payroll-parameters.manage',
        'payroll-concepts.view',
        'payroll-concepts.manage',
        'holidays.view',
        'holidays.manage',
    ];

    /**
     * Quién recibe qué. `administrador-general` no aparece: ver la nota de cabecera.
     *
     * @var array<string, list<string>>
     */
    private array $rolePermissions = [
        'talento-humano' => [
            'employees.view', 'employees.create', 'employees.update', 'employees.delete',
            'employee-salaries.view',
            'employee-qr.view', 'employee-qr.create', 'employee-qr.update',
            'attendance.view',
            'payroll-parameters.view', 'payroll-parameters.manage',
            'payroll-concepts.view', 'payroll-concepts.manage',
            'holidays.view', 'holidays.manage',
        ],

        // Lo mínimo para operar la puerta: marcar y ver lo que marcó. Sin sueldos y sin
        // poder editar a la persona que escanea.
        'porteria' => [
            'employees.view',
            'attendance.view', 'attendance.record',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        Tenant::query()->each(function (Tenant $tenant): void {
            setPermissionsTeamId($tenant->id);

            foreach ($this->rolePermissions as $roleName => $permissions) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'team_id' => $tenant->id,
                ]);

                foreach ($permissions as $permissionName) {
                    $permissionId = Permission::query()
                        ->where('name', $permissionName)
                        ->where('guard_name', 'web')
                        ->value('id');

                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $role->id,
                    ]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()
            ->whereIn('name', array_keys($this->rolePermissions))
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();

        Permission::query()
            ->whereIn('name', $this->permissionNames)
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
