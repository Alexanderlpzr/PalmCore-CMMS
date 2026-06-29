<?php

namespace App\Actions\Tenants;

use App\Models\Area;
use App\Models\Plant;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\DB;

class ProvisionTenantBaseStructure
{
    /**
     * Default process-flow areas for a new palm-oil extraction tenant.
     * sort_order uses multiples of 10 to leave room for later gap insertion.
     *
     * @var list<array{code: string, name: string, sort_order: int}>
     */
    private const DEFAULT_AREAS = [
        ['code' => 'REC-01', 'name' => 'Recepción', 'sort_order' => 10],
        ['code' => 'EST-01', 'name' => 'Esterilización', 'sort_order' => 20],
        ['code' => 'DIG-01', 'name' => 'Digestión', 'sort_order' => 30],
        ['code' => 'PRE-01', 'name' => 'Prensado', 'sort_order' => 40],
        ['code' => 'CLA-01', 'name' => 'Clarificación', 'sort_order' => 50],
        ['code' => 'PAL-01', 'name' => 'Palmistería', 'sort_order' => 60],
        ['code' => 'TAL-01', 'name' => 'Taller', 'sort_order' => 70],
    ];

    /**
     * Provision a brand-new tenant with a default plant, process areas, and the
     * full per-tenant role/permission matrix so it is usable immediately.
     *
     * Global scopes are bypassed and tenant_id is set explicitly because this
     * runs outside any CurrentTenant context (e.g. a super admin creating a
     * tenant from the panel), where BelongsToTenant cannot auto-fill tenant_id.
     */
    public function handle(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            $plant = Plant::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'PLT-01'],
                ['name' => 'Planta Principal', 'is_active' => true],
            );

            foreach (self::DEFAULT_AREAS as $area) {
                Area::withoutGlobalScopes()->firstOrCreate(
                    ['plant_id' => $plant->id, 'code' => $area['code']],
                    [
                        'tenant_id' => $tenant->id,
                        'name' => $area['name'],
                        'sort_order' => $area['sort_order'],
                        'is_active' => true,
                    ],
                );
            }

            // Guarantee the full permission catalogue exists before roles are
            // synced: TenantRolesSeeder::syncPermissions() throws if any matrix
            // permission is missing, so provisioning must not depend on a prior
            // migration/seeder having run. Both seeders are idempotent.
            (new PermissionSeeder)->run();
            (new TenantRolesSeeder)->run($tenant);
        });
    }
}
