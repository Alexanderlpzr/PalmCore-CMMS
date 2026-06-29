<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Home CMS authorization rollout.
 *
 * The Inicio portal's content resources (Carrusel / Contenido) are now gated by
 * AnnouncementPolicy / CarouselSlidePolicy, which require dedicated permissions.
 * Seeders do not run on deploy, so this migration provisions the permissions and
 * grants them to every existing "administrador-general" role (one per tenant)
 * to avoid admins losing access after the policies ship. Idempotent.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private array $permissions = [
        'announcements.view',
        'announcements.create',
        'announcements.update',
        'announcements.delete',
        'carousel-slides.view',
        'carousel-slides.create',
        'carousel-slides.update',
        'carousel-slides.delete',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $permissionIds = Permission::query()
            ->whereIn('name', $this->permissions)
            ->where('guard_name', 'web')
            ->pluck('id');

        // role_has_permissions is (permission_id, role_id); roles are already
        // team-scoped by their own team_id, so the pivot needs no team column.
        // Insert straight into the pivot to avoid Spatie's relation access
        // (lazy loading is disabled in strict mode). insertOrIgnore = idempotent.
        $roleIds = Role::query()->where('name', 'administrador-general')->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->whereIn('name', $this->permissions)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
