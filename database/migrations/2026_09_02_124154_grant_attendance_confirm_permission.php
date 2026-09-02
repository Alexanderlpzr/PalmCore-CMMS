<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * La facultad de firmar las horas del reloj, para los tenants que ya existen.
 *
 * Va aparte de `attendance.record` porque son responsabilidades distintas: portería
 * registra que alguien cruzó la puerta, y eso es un hecho; confirmar las horas es afirmar
 * que son las que se van a pagar, y eso es una decisión. Quien marca no firma.
 *
 * Hoy la recibe talento humano. El sitio natural sería un rol de supervisor de planta,
 * pero inventar ese rol antes de que alguien lo pida es adivinar cómo trabaja la empresa.
 * Cuando exista, se le mueve el permiso y no hay nada más que cambiar.
 */
return new class extends Migration
{
    private const PERMISSION = 'attendance.confirm';

    /** @var array<int, string> */
    private array $roleNames = ['talento-humano'];

    public function up(): void
    {
        Permission::findOrCreate(self::PERMISSION, 'web');

        $permissionId = Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        $roleIds = Role::query()->whereIn('name', $this->roleNames)->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            Permission::query()->where('id', $permissionId)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
