<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol «proceso» pasa a llamarse «produccion» (Producción), con lo mismo de antes: recibe
 * y corrige los hallazgos de su área y mide los tanques de aceite. Quien lo tenía lo
 * conserva —se renombra el mismo rol—, y las partes de hallazgos «proceso» pasan a
 * «produccion». Pedido 2026-11-19. Idempotente.
 *
 * Devuelve también oil-tanks.record, que una migración del mismo día le había quitado.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['sst-findings.view', 'sst-findings.execute', 'oil-tanks.record'];

    public function up(): void
    {
        $permissionIds = collect($this->permissions)
            ->map(fn (string $name): int|string => Permission::findOrCreate($name, 'web')->id);

        Tenant::query()->each(function (Tenant $tenant) use ($permissionIds): void {
            setPermissionsTeamId($tenant->id);

            $old = Role::query()->where('name', 'proceso')->where('guard_name', 'web')->where('team_id', $tenant->id)->first();
            $new = Role::query()->where('name', 'produccion')->where('guard_name', 'web')->where('team_id', $tenant->id)->first();

            if ($old !== null && $new === null) {
                $old->update(['name' => 'produccion']);
                $new = $old;
            } elseif ($old !== null) {
                // Ya existían los dos: quien tenía «proceso» queda con «produccion».
                DB::table('model_has_roles')->where('role_id', $old->id)->update(['role_id' => $new->id]);
                DB::table('role_has_permissions')->where('role_id', $old->id)->delete();
                $old->delete();
            }

            $new ??= Role::query()->create(['name' => 'produccion', 'guard_name' => 'web', 'team_id' => $tenant->id]);

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $new->id]);
            }
        });

        DB::table('sst_finding_tasks')->where('area', 'proceso')->update(['area' => 'produccion']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'produccion')->update(['name' => 'proceso']);
        DB::table('sst_finding_tasks')->where('area', 'produccion')->update(['area' => 'proceso']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
