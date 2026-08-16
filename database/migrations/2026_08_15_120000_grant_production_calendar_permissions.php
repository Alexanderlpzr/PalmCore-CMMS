<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El calendario de producción pasa a manos de la planta.
 *
 * `ProductionCalendarResource` estuvo escondido tras `is_super_admin` mientras no
 * hubo forma cómoda de cargar el dato; ahora consulta la policy, que exige
 * `production-calendar.view` para ver y `production-calendar.manage` para escribir.
 * Ambos permisos ya están en `PermissionSeeder` y en la matriz de
 * `TenantRolesSeeder`, pero **los seeders no corren en deploy**: los tenants creados
 * antes de esto no los tienen, y Filament evalúa la policy fila por fila —Spatie
 * lanza excepción si el permiso ni siquiera existe—, así que sin esta migración la
 * pantalla revienta en producción en vez de simplemente ocultarse.
 *
 * Idempotente. `down()` revoca la concesión pero **no borra los permisos**: están
 * declarados en el seeder y la policy los nombra, así que borrarlos rompería más de
 * lo que revierte.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissionNames = [
        'production-calendar.view',
        'production-calendar.manage',
    ];

    /** @var array<int, string> */
    private array $roleNames = [
        'administrador-general',
    ];

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
        $roleIds = Role::query()->whereIn('name', $this->roleNames)->pluck('id');

        $permissionIds = Permission::query()
            ->whereIn('name', $this->permissionNames)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
