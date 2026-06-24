<?php

namespace Database\Seeders;

use App\Actions\Tenants\ProvisionTenantBaseStructure;
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

        // Default plant + process areas + per-tenant role matrix.
        app(ProvisionTenantBaseStructure::class)->handle($tenant);
    }
}
