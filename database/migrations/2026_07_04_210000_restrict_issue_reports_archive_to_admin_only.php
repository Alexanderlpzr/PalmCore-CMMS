<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Restricts issue report deletion to `administrador-general` only.
 *
 * `issue-reports.archive` was originally granted to plant-manager,
 * ingeniero-mantenimiento and supervisor alongside administrador-general.
 * Product decision: only the tenant Administrator should be able to delete
 * (soft-delete) issue reports. Revokes the permission from the other three
 * roles across all tenants; administrador-general keeps it. Idempotent.
 */
return new class extends Migration
{
    private string $permissionName = 'issue-reports.archive';

    /** @var array<int, string> */
    private array $roleNamesToRevoke = [
        'plant-manager',
        'ingeniero-mantenimiento',
        'supervisor',
    ];

    public function up(): void
    {
        $permissionId = Permission::query()
            ->where('name', $this->permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = Role::query()->whereIn('name', $this->roleNamesToRevoke)->pluck('id');

        DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permissionId = Permission::query()
            ->where('name', $this->permissionName)
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = Role::query()->whereIn('name', $this->roleNamesToRevoke)->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
