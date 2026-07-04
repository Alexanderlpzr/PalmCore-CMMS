<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Issue Reports archive/restore rollout.
 *
 * EquipmentIssueReportPolicy::delete()/restore() now require the
 * `issue-reports.archive` permission. Seeders do not run on deploy, so this
 * migration provisions the permission and grants it to every existing role
 * (across all tenants) that already had `issue-reports.acknowledge`, to avoid
 * crashing the Reportes de Novedad list (Filament evaluates the policy for
 * every row's action visibility, and Spatie throws if the permission name
 * does not exist at all). Idempotent.
 */
return new class extends Migration
{
    private string $permissionName = 'issue-reports.archive';

    /** @var array<int, string> */
    private array $roleNames = [
        'administrador-general',
        'plant-manager',
        'ingeniero-mantenimiento',
        'supervisor',
    ];

    public function up(): void
    {
        Permission::findOrCreate($this->permissionName, 'web');

        $permissionId = Permission::query()
            ->where('name', $this->permissionName)
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->where('name', $this->permissionName)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
