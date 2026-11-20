<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * El rol «direccion-produccion» (Dirección de Producción) en cada empresa: solo consulta.
 * Ve los tanques de aceite, la maquila y el inventario de palmiste (production.view, que
 * nace aquí), el Dashboard con paros y energía, el calendario de producción y los
 * hallazgos; no mide, no registra ni corrige nada. Pedido 2026-11-19. Idempotente.
 *
 * Solo crea el rol: quién lo lleva lo asigna Fronda desde el panel de plataforma.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = ['production.view', 'production-calendar.view', 'downtime-events.view', 'energy.view', 'sst-findings.view'];

    public function up(): void
    {
        $permissionIds = collect($this->permissions)
            ->map(fn (string $name): int|string => Permission::findOrCreate($name, 'web')->id);

        Tenant::query()->each(function (Tenant $tenant) use ($permissionIds): void {
            setPermissionsTeamId($tenant->id);

            $role = Role::query()->firstOrCreate(['name' => 'direccion-produccion', 'guard_name' => 'web', 'team_id' => $tenant->id]);

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $role->id]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roleIds = Role::query()->where('name', 'direccion-produccion')->pluck('id');

        DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
        DB::table('model_has_roles')->whereIn('role_id', $roleIds)->delete();
        Role::query()->whereIn('id', $roleIds)->delete();
        Permission::query()->where('name', 'production.view')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
