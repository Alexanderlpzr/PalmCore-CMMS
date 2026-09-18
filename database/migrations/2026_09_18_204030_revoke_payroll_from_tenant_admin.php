<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * La nómina vuelve a quedar fuera del administrador del tenant.
 *
 * Es la tercera vuelta, y esta vez con una razón que antes no existía: El Pajuil tiene
 * ahora una persona de RRHH con su propia cuenta (`talento-humano`). El argumento para
 * darle la nómina al administrador —«son la misma persona, no mantengamos dos cuentas»—
 * dejó de ser cierto, y el original vuelve a pesar: el ingeniero de mantenimiento ve los
 * equipos de su planta, no el salario ni el examen médico de cada trabajador.
 *
 * Deshace {@see 2026_09_02_190102_grant_payroll_to_tenant_admin}. Solo se le quitan al
 * rol `administrador-general`: los permisos siguen existiendo y `talento-humano` y
 * `porteria` conservan los suyos. Que el administrador no pueda asignarse el rol de
 * RRHH lo garantiza que Usuarios y Roles son pantallas solo del superadministrador.
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
        DB::table('role_has_permissions')
            ->whereIn('permission_id', $this->permissionIds())
            ->whereIn('role_id', $this->adminRoleIds())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->permissionIds() as $permissionId) {
            foreach ($this->adminRoleIds() as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return Collection<int, string> */
    private function adminRoleIds(): Collection
    {
        return Role::query()->where('name', 'administrador-general')->pluck('id');
    }

    /** @return Collection<int, string> */
    private function permissionIds(): Collection
    {
        return Permission::query()
            ->whereIn('name', $this->permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');
    }
};
