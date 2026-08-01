<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Retires eight of the nine tenant roles, leaving only `administrador-general`.
 *
 * The maintenance engineer administers his own tenant through that single role;
 * we administer the platform through the `is_super_admin` flag, which is not a
 * Spatie role and is untouched here. Policies authorize on permissions rather
 * than role names, so nothing in the authorization layer depends on the roles
 * being dropped.
 *
 * Users holding a retired role are deliberately left with no role at all — they
 * lose panel access until an administrator assigns them `administrador-general`
 * by hand. Auto-promoting them would hand a former operator or storekeeper the
 * full 121-permission set. Runs across every tenant; idempotent.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $retiredRoles = [
        'gerencia',
        'plant-manager',
        'ingeniero-mantenimiento',
        'supervisor',
        'tecnico',
        'almacenista',
        'compras',
        'operario',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', $this->retiredRoles)
            ->where('guard_name', 'web')
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        // Explicit rather than relying on FK cascades, so the intent survives a
        // schema dump that omits them.
        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        DB::table('roles')->whereIn('id', $roleIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Irreversible by design: the role → permission matrix these roles carried
     * was removed from TenantRolesSeeder in the same change, so there is nothing
     * left to rebuild them from, and the user assignments are gone.
     */
    public function down(): void
    {
        //
    }
};
