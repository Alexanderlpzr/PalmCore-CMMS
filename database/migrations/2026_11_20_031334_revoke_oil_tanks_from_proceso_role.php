<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Proceso deja de ver producción: se le quita oil-tanks.record —la página «Tanques de
 * aceite» y sus avisos—, que tenía desde el 2026-11-18. Los tanques los mide la Directora
 * Administrativa. Pedido 2026-11-19. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_has_permissions')
            ->whereIn('role_id', Role::query()->where('name', 'proceso')->pluck('id'))
            ->whereIn('permission_id', Permission::query()->where('name', 'oil-tanks.record')->pluck('id'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::query()->where('name', 'oil-tanks.record')->where('guard_name', 'web')->first();

        if ($permission === null) {
            return;
        }

        foreach (Role::query()->where('name', 'proceso')->pluck('id') as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permission->id, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
