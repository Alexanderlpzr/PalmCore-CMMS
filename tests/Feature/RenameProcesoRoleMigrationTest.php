<?php

use App\Domain\Safety\Enums\ResponsibleArea;
use App\Models\Role;
use App\Models\SstFinding;
use App\Models\SstFindingTask;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/*
 * El rol «proceso» pasa a «produccion» (2026-11-19): quien lo tenía lo conserva con el
 * nombre nuevo, recupera los tanques de aceite y sus partes de hallazgos cambian de área.
 * Se arma el estado de antes y se corre la migración.
 */

it('renames the process role to production keeping its people, tanks included', function (): void {
    $this->seed(PermissionSeeder::class);
    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->id);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $proceso = Role::query()->create(['name' => 'proceso', 'guard_name' => 'web', 'team_id' => $tenant->id]);
    $proceso->givePermissionTo(['sst-findings.view', 'sst-findings.execute']);

    $cuenta = User::factory()->create(['is_active' => true]);
    $cuenta->tenants()->attach($tenant->id, ['joined_at' => now()]);
    $cuenta->assignRole('proceso');

    $finding = SstFinding::factory()->create(['tenant_id' => $tenant->id]);
    $parte = SstFindingTask::query()->create(['tenant_id' => $tenant->id, 'sst_finding_id' => $finding->id, 'area' => 'mantenimiento', 'status' => 'pendiente']);
    DB::table('sst_finding_tasks')->where('id', $parte->id)->update(['area' => 'proceso']);

    $migration = require database_path('migrations/2026_11_20_034315_rename_proceso_role_to_produccion.php');
    $migration->up();
    $migration->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    setPermissionsTeamId($tenant->id);
    $cuenta = $cuenta->fresh();

    expect(Role::query()->where('team_id', $tenant->id)->pluck('name')->all())->toBe(['produccion'])
        ->and($cuenta->hasRole('produccion'))->toBeTrue()
        ->and($cuenta->hasPermissionTo('oil-tanks.record'))->toBeTrue()
        ->and($cuenta->hasPermissionTo('sst-findings.execute'))->toBeTrue()
        ->and($parte->fresh()->area)->toBe(ResponsibleArea::Produccion)
        ->and(Role::humanizeName('produccion'))->toBe('Producción');
});
