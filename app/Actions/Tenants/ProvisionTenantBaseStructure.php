<?php

namespace App\Actions\Tenants;

use App\Models\Area;
use App\Models\Plant;
use App\Models\Tenant;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantEnergyMetersSeeder;
use Database\Seeders\TenantInventorySeeder;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Support\Facades\DB;

class ProvisionTenantBaseStructure
{
    /**
     * Secciones del flujo de proceso de una extractora de aceite de palma, en el
     * orden en que la fruta las recorre. sort_order va de diez en diez para poder
     * intercalar después sin renumerar.
     *
     * Son las secciones que la planta usa de verdad, tomadas del inventario de
     * campo de El Pajuil. Antes había siete genéricas con «Digestión» y
     * «Prensado» como áreas propias —en la planta ambas son equipos dentro de
     * Extracción— y faltaban Desfrutado, Raquis, Desfibrado y Cogeneración, que
     * concentran casi la mitad de los equipos.
     *
     * @var list<array{code: string, name: string, sort_order: int}>
     */
    private const DEFAULT_AREAS = [
        ['code' => 'REC-01', 'name' => 'Recepción', 'sort_order' => 10],
        ['code' => 'EST-01', 'name' => 'Esterilización', 'sort_order' => 20],
        ['code' => 'DFR-01', 'name' => 'Desfrutado', 'sort_order' => 30],
        ['code' => 'RAQ-01', 'name' => 'Raquis', 'sort_order' => 40],
        ['code' => 'EXT-01', 'name' => 'Extracción', 'sort_order' => 50],
        ['code' => 'CLA-01', 'name' => 'Clarificación', 'sort_order' => 60],
        ['code' => 'PAL-01', 'name' => 'Palmistería', 'sort_order' => 70],
        ['code' => 'DFB-01', 'name' => 'Desfibrado', 'sort_order' => 80],
        ['code' => 'COG-01', 'name' => 'Cogeneración', 'sort_order' => 90],
    ];

    /**
     * Provision a brand-new tenant with a default plant, process areas, the base
     * equipment inventory, and the full per-tenant role/permission matrix so it
     * is usable immediately.
     *
     * Global scopes are bypassed and tenant_id is set explicitly because this
     * runs outside any CurrentTenant context (e.g. a super admin creating a
     * tenant from the panel), where BelongsToTenant cannot auto-fill tenant_id.
     */
    public function handle(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant): void {
            // La planta por defecto se llama como la extractora misma (el tenant),
            // no «Planta Principal» — así el usuario siempre tiene una opción lista
            // que reconoce de inmediato, sin tener que crearla ni renombrarla.
            $plant = Plant::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'PLT-01'],
                ['name' => $tenant->name, 'is_active' => true],
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

            // Depende de las áreas de arriba: cada equipo se cuelga de la suya.
            (new TenantInventorySeeder)->run($tenant, $plant);

            // Después del inventario: dos de los tres contadores se enlazan al equipo
            // que los genera, y ese equipo tiene que existir ya.
            (new TenantEnergyMetersSeeder)->run($tenant, $plant);

            // Guarantee the full permission catalogue exists before roles are
            // synced: TenantRolesSeeder::syncPermissions() throws if any matrix
            // permission is missing, so provisioning must not depend on a prior
            // migration/seeder having run. Both seeders are idempotent.
            (new PermissionSeeder)->run();
            (new TenantRolesSeeder)->run($tenant);
        });
    }
}
