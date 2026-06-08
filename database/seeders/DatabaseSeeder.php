<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            SuperAdminSeeder::class,
            DemoTenantSeeder::class,   // creates tenant + plant + areas + 9 roles
            AdminTenantSeeder::class,  // creates admin user, attaches to tenant, assigns role
        ]);
    }
}
