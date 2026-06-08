<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Plant;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'el-pajuil'],
            [
                'name' => 'Extractora El Pajuil',
                'country_code' => 'COL',
                'timezone' => 'America/Bogota',
                'locale' => 'es_CO',
                'is_active' => true,
            ]
        );

        $plant = Plant::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PLT-01'],
            [
                'name' => 'Planta Principal',
                'is_active' => true,
            ]
        );

        // Process flow areas — sort_order in multiples of 10 to allow gap insertion.
        $areas = [
            ['code' => 'REC-01', 'name' => 'Recepción',     'sort_order' => 10],
            ['code' => 'EST-01', 'name' => 'Esterilización', 'sort_order' => 20],
            ['code' => 'DIG-01', 'name' => 'Digestión',      'sort_order' => 30],
            ['code' => 'PRE-01', 'name' => 'Prensado',       'sort_order' => 40],
            ['code' => 'CLA-01', 'name' => 'Clarificación',  'sort_order' => 50],
            ['code' => 'PAL-01', 'name' => 'Palmistería',    'sort_order' => 60],
            ['code' => 'TAL-01', 'name' => 'Taller',         'sort_order' => 70],
        ];

        foreach ($areas as $area) {
            Area::withoutGlobalScopes()->firstOrCreate(
                ['plant_id' => $plant->id, 'code' => $area['code']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $area['name'],
                    'sort_order' => $area['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        $this->call(TenantRolesSeeder::class, false, compact('tenant'));
    }
}
