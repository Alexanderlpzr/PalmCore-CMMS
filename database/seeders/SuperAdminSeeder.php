<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPER_ADMIN_PASSWORD', 'Admin123');

        if (app()->isProduction() && $password === 'Admin123') {
            $this->command->warn('SECURITY: SuperAdminSeeder using default password in production. Set SUPER_ADMIN_PASSWORD env variable.');
        }

        User::firstOrCreate(
            ['email' => 'superadmin@palmcore.app'],
            [
                'name' => 'Fronda Admin',
                'password' => Hash::make($password),
                'is_active' => true,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
