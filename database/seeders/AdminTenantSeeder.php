<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AdminTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'el-pajuil')->firstOrFail();

        $adminPassword = env('ADMIN_PASSWORD', 'Admin123');

        if (app()->isProduction() && $adminPassword === 'Admin123') {
            $this->command->warn('SECURITY: AdminTenantSeeder using default password in production. Set ADMIN_PASSWORD env variable.');
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@elpajuil.demo'],
            [
                'name' => 'Administrador El Pajuil',
                'password' => Hash::make($adminPassword),
                'is_active' => true,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        // Attach to tenant only once — the partial index enforces one primary per user.
        if (! $admin->tenants()->where('tenants.id', $tenant->id)->exists()) {
            $admin->tenants()->attach($tenant->id, [
                'is_primary_tenant' => true,
                'is_owner' => true,
                'joined_at' => now(),
            ]);
        }

        // Assign role within tenant context.
        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! $admin->hasRole('administrador-general')) {
            $admin->assignRole('administrador-general');
        }
    }
}
